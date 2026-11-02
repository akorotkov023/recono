<?php


namespace App\Controller;


use App\Entity\BalanceErrorLog;
use App\Form\BalanceProcessType;
use App\Repository\BalanceConfigRepository;
use App\Repository\BalanceErrorLogRepository;
use App\Repository\BalanceProcessRepository;
use App\Repository\RegistryCommandRepository;
use App\Service\ApiSession;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BalanceProcessController extends AbstractController
{
    protected ApiSession $api;

    public function __construct(ApiSession $api) {
        $this->api = $api;
    }

    #[Route('/balance/process', name: 'app_balance_process')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request,
        BalanceProcessRepository $balanceProcessRepository,
        RegistryCommandRepository $registryCommandRepository,
        BalanceConfigRepository $balanceConfigRepository,
        UserInterface $user, NotifierInterface $notifier): Response
    {

        $activeConfigBalance = $balanceConfigRepository->findAllActive();
        $data = [];
        $data['отправка всем'] = 0;
        foreach ($activeConfigBalance as $config) {
            $data[$config->getPartnerName()] = $config->getId();
        }

        $startData = new DateTime('- 67 days', new DateTimeZone("+3"));
        $endData = new DateTime('now', new DateTimeZone("+3"));
        $createBalanceData = new DateTime('now', new DateTimeZone("+3"));
        $createBalanceData->modify('-1 day');

        $form = $this->createForm(BalanceProcessType::class, [
            'start' => $startData,
            'end' => $endData,
            'create_data' => $createBalanceData,
        ],[
            'help_attr' => $data
        ]);
        $form->handleRequest($request);

        $start = $form->get('start')->getData();
        $end = $form->get('end')->getData();
        $date = $form->get('create_data')->getData();

        //Создать процесс за дату
        if ($form->getClickedButton() && 'create_balance' === $form->getClickedButton()->getName()) {

            $command = 'balance_create';
            $options = '--id='.$form->get('choose')->getData();

            $this->runCommandWithLog($command, $date->format('Y-m-d'), $options, $user, $notifier);

        }

        $data = $balanceProcessRepository->findByExampleField($start->format('Y-m-d'), $end->format('Y-m-d'));
        $dataReconcil = $registryCommandRepository->findByNotVerifiedCommand($start->format('Y-m-d'), $end->format('Y-m-d'));


//        foreach () {
//
//            $dataCheckSum[] = $registryCommandRepository->findCheckSumCounterInWas($start->format('Y-m-d'), $end->format('Y-m-d'));
//
//        }

//        dd($data);

        $dataAll = [];
        foreach ($data as $record) {
            // Добавляет на форму одновременный запуск команд за дату
//            if ($record->getStatus() === 'new') {
//                $dataAll[$record->getOnDate()->format('Y-m-d')]['new'] = true;
//            } else {
//                $dataAll[$record->getOnDate()->format('Y-m-d')]['new'] = false;
//            }
//            if ($record->getStatus() === 'created') {
//                $dataAll[$record->getOnDate()->format('Y-m-d')]['created'] = true;
//            } else {
//                $dataAll[$record->getOnDate()->format('Y-m-d')]['created'] = false;
//            }
//            if ($record->getStatus() === 'checked') {
//                $dataAll[$record->getOnDate()->format('Y-m-d')]['checked'] = true;
//            } else {
//                $dataAll[$record->getOnDate()->format('Y-m-d')]['checked'] = false;
//            }
            $dataAll[$record->getOnDate()->format('Y-m-d')]['processIds'][$record->getId()] = $record;
        }
        $dataAll2 = [];
        foreach ($dataReconcil as $record) {
            $dataAll2[$record->getOnDate()->format('Y-m-d')]['commandNotVerified'][$record->getId()] = $record;
        }

        $arr = array_merge_recursive($dataAll, $dataAll2);
        //TODO Сделать проверку на вчерашнюю сумму по балансу
//        foreach ($arr as $record => $value ) {
//            dd($value);
//        }

        return $this->render('balance_process/index.html.twig', [
            'report_search' => $form->createView(),
            'data' => $arr
        ]);
    }

    #[Route('/balance/process/error', name: 'app_balance_process_error')]
    #[IsGranted('ROLE_ADMIN')]
    public function show(BalanceErrorLogRepository $balanceErrorLogRepository): Response
    {
        $data = $balanceErrorLogRepository->findAll();
        $all = 0;
        foreach ($data as $item) {
            $all .= ',' . $item->getId();
        }
        return $this->render('balance_process/error.html.twig', [
            'data' => $data,
            'ids' => $all
        ]);
    }

    #[Route('/balance/process/error/{id}/delete', name: 'app_balance_process_error_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(BalanceErrorLog $user, EntityManagerInterface $entityManager, BalanceProcessRepository $balanceProcessRepository): Response
    {
        $process = $balanceProcessRepository->find($user->getProcessId());
        $process->setStatus('new');
        $entityManager->persist($process);
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_balance_process_error');
    }

    #[Route('/balance/process/error/delete/all/{id}', name: 'app_balance_process_error_delete_all')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAll(Request $request, BalanceErrorLogRepository $errorLog, EntityManagerInterface $entityManager, BalanceProcessRepository $balanceProcessRepository): Response
    {
        $ids = $request->get('id');
        $arr[] = explode( ',', $ids);
        foreach ($arr[0] as $key => $val) {
            if ($val <> 0) {
                $log = $errorLog->find((int)$val);
                $process = $balanceProcessRepository->find($log->getProcessId());
                $process->setStatus('new');
                $entityManager->persist($process);
                $entityManager->remove($log);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_balance_process');
    }

    #[Route('/balance/process/restart/{date}/create', name: 'app_balance_process_restart_create')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'balance_create';
        $date = $request->get('date');
        $options = '--id='.$request->get('id');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_balance_process');
    }

    #[Route('/balance/process/restart/{date}/check/{process}', name: 'app_balance_process_restart_check')]
    #[IsGranted('ROLE_ADMIN')]
    public function check(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'balance_check';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_balance_process');
    }

    #[Route('/balance/process/restart/{date}/finish/{process}', name: 'app_balance_process_finish')]
    #[IsGranted('ROLE_ADMIN')]
    public function finish(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'balance_finish';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_balance_process');
    }

    #[Route('/balance/process/{date}/cancel/{process}', name: 'app_balance_process_cancel')]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'balance_cancel';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_balance_process');
    }

    #[Route('/balance/process/{date}/reload/{process}', name: 'app_balance_process_reload')]
    #[IsGranted('ROLE_ADMIN')]
    public function reset(Request $request, UserInterface $user, NotifierInterface $notifier): Response
    {
        $command = 'balance_partner_reload';
        $date = $request->get('date');
        $options = '--process='.$request->get('process');

        $this->runCommandWithLog($command, $date, $options, $user, $notifier);

        return $this->redirectToRoute('app_balance_process');
    }

    private function runCommandWithLog($command, $date, $options, UserInterface $user, $notifier): void
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
