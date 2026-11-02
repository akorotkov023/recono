<?php


namespace App\Controller;


use App\Entity\EmailErrorLog;
use App\Form\EmailProcessType;
use App\Helper\BaseHelper;
use App\Repository\EmailConfigRepository;
use App\Repository\EmailErrorLogRepository;
use App\Repository\EmailProcessRepository;
use App\Repository\RegistryCommandRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class EmailProcessController extends AbstractController
{
    #[Route('/email/process', name: 'app_email_process')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request,
        EmailProcessRepository $emailProcessRepository,
        EmailConfigRepository $emailConfigRepository,
        RegistryCommandRepository $registryCommandRepository,
        BaseHelper $helper,
        UserInterface $user, NotifierInterface $notifier): Response
    {

        $activeConfigEmail = $emailConfigRepository->findAllActive();
        $data = [];
        $data['отправка всем'] = 0;
        foreach ($activeConfigEmail as $config) {
            $data[$config->getSlug()] = $config->getId();
        }

        $startData = new DateTime('- 7 days', new DateTimeZone("+3"));
        $startData->modify('-1 month');
        $endData = new DateTime('now', new DateTimeZone("+3"));
        $createRegData = new DateTime('now', new DateTimeZone("+3"));
        $createRegData->modify('-1 day');

        $form = $this->createForm(EmailProcessType::class, [
            'start' => $startData,
            'end' => $endData,
            'create_data' => $createRegData,
        ],[
            'help_attr' => $data
        ]);
        $form->handleRequest($request);

        $start = $form->get('start')->getData();
        $end = $form->get('end')->getData();
        $date = $form->get('create_data')->getData();
        $date = $date->format('Y-m-d');

        //Создать процесс за дату
        if ($form->getClickedButton() && 'create_registry' === $form->getClickedButton()->getName()) {
            $command = 'email_create';
            $options = '--id='.$form->get('choose')->getData();

            $this->runCommandWithLog($command, $date, $options, $user, $notifier);
        }

        if ($form->getClickedButton() && 'check_registry' === $form->getClickedButton()->getName()) {
            $command = 'email_check';
            $options = '--id='.$form->get('choose')->getData();

            $this->runCommandWithLog($command, $date, $options, $user, $notifier);
        }

        if ($form->getClickedButton() && 'send_registry' === $form->getClickedButton()->getName()) {
            $command = 'email_finish';
            $options = '--id='.$form->get('choose')->getData();

            $this->runCommandWithLog($command, $date, $options, $user, $notifier);
        }

        $data = $emailProcessRepository->findByExampleField($start->format('Y-m-d'), $end->format('Y-m-d'));
        $dataRegistry = $registryCommandRepository->findAllCommandsByDate($start->format('Y-m-d'), $end->format('Y-m-d'));
        $refundedRegistry = $helper->getRefunded($start->format('Y-m-d'), $end->format('Y-m-d'));
//        dd($refundedRegistry);

        $dataAll = [];
        $dataAllAmounts = [];
        $refundAllAmounts = [];

        foreach ($data as $record) {
//            dd($record, $record->getEmailConfigId()->getIsTotalAmountChecked());
            $dataAll[$record->getOnDate()->format('Y-m-d')]['processIds'][$record->getId()] = $record;
        }
        foreach ($dataRegistry as $record) {
            $dataAllAmounts[$record->getOnDate()->format('Y-m-d')]['amounts'][$record->getId()] = $record;
        }
        foreach ($refundedRegistry as $record) {
//            dd($record['date']);
            $refundAllAmounts[$record['date']]['refunds'] = $record['sum'];
        }
        $result = [];

        foreach ($dataAll as $date => $dataA) {
            $result[$date] = array_merge(
                $dataA,
                $dataAllAmounts[$date] ?? [],
                $refundAllAmounts[$date] ?? []
            );
        }
//        dd($result);
        krsort($result, SORT_STRING);

        return $this->render('email_process/index.html.twig', [
            'report_search' => $form->createView(),
            'data' => $result
        ]);
    }

    #[Route('/email/process/error', name: 'app_email_process_error')]
    #[IsGranted('ROLE_ADMIN')]
    public function show(EmailErrorLogRepository $EmailErrorLogRepository): Response
    {
        $data = $EmailErrorLogRepository->findAll();
        $all = 0;
        foreach ($data as $item) {
            $all .= ',' . $item->getId();
        }
        return $this->render('email_process/error.html.twig', [
            'data' => $data,
            'ids' => $all
        ]);
    }

    #[Route('/email/process/error/{id}/delete', name: 'app_email_process_error_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        EmailErrorLog $user,
        EntityManagerInterface $entityManager,
        EmailProcessRepository $emailProcessRepository): Response
    {
        $process = $emailProcessRepository->find($user->getProcessId());
        $process->setStatus('new');
        $entityManager->persist($process);
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_email_process_error');
    }

    #[Route('/email/process/error/delete/all/{id}', name: 'app_email_process_error_delete_all')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAll(
        Request $request,
        EmailErrorLogRepository $errorLog,
        EntityManagerInterface $entityManager,
        EmailProcessRepository $emailProcessRepository): Response
    {
        $ids = $request->get('id');
        $arr[] = explode( ',', $ids);
        foreach ($arr[0] as $key => $val) {
            if ($val <> 0) {
                $log = $errorLog->find((int)$val);
                $process = $emailProcessRepository->find($log->getProcessId());
                $process->setStatus('new');
                $entityManager->persist($process);
                $entityManager->remove($log);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_email_process');
    }

    #[Route('/email/process/restart/{date}/create/{id}', name: 'app_email_process_restart_create')]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_USER')")]
    public function create(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'email_create';
        $date = $request->get('date');
        $options = '--id='.$request->get('id');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_email_process');
    }

    #[Route('/email/process/restart/{date}/check/{process}', name: 'app_email_process_restart_check')]
    #[IsGranted('ROLE_ADMIN')]
    public function check(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'email_check';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_email_process');
    }

    #[Route('/email/process/restart/{date}/finish/{process}', name: 'app_email_process_finish')]
    #[IsGranted('ROLE_ADMIN')]
    public function finish(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {

        $command = 'email_finish';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_email_process');
    }


    #[Route('/email/process/{date}/cancel/{process}', name: 'app_email_process_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'email_cancel';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_email_process');
    }

    private function runCommandWithLog($command, $date, $options, UserInterface $user, $notifier)
    {
        $process = new Process(['/usr/local/bin/php', '../bin/console', $command, $date, $options]);
        $process->start();
        $stderr = '';

        foreach ($process as $type => $data) {
            if ($user->getRoot()) {
                if ($process::OUT === $type) {
                    $stderr = $stderr . ' ' . $data . PHP_EOL;
                } else {
                    $stderr = $stderr . ' ' . $data . PHP_EOL;
                }
            } else {
                $stderr = 'ℹ️ Done!';
            }
        }

        if ($user->getRoot()) {
            $notifier->send(new Notification($stderr, ['browser']));
        }
    }

}
