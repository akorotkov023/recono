<?php

namespace App\Controller;


use App\Entity\RegistryErrorLog;
use App\Enum\RegistryCommandStatusEnum;
use App\Form\FilterType;
use App\Form\RegistryCommandMainType;
use App\Form\RegistryCommandType;
use App\Form\ToolsType;
use App\Helper\BaseHelper;
use App\Message\ExtractedRegistryMessage;
use App\Repository\CollationIssuesLogRepository;
use App\Repository\CollationIssuesRepository;
use App\Repository\RegistryCommandRepository;
use App\Repository\RegistryConfigRepository;
use App\Repository\RegistryErrorLogRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RegistryCommandController extends AbstractController
{

    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus, private string $app)
    {
        $this->bus = $bus;
    }

    #[Route('/registry_pre', name: 'app_registry_pre')]
    #[IsGranted('ROLE_ADMIN')]
    public function pre(
        Request $request,
        RegistryConfigRepository $registryConfigRepository,
    ): Response
    {
        $activeProviders = $registryConfigRepository->findAllActiveArray();
        $providerChoices = array_combine($activeProviders, $activeProviders);

        if (!$request->get('start')) {
            $provider = $activeProviders[0] ?? '';
            $startData = new DateTime('- 10 days', new DateTimeZone("+3"));
            $endData = new DateTime($request->get('end'), new DateTimeZone("+3"));
        } else {
            $provider = $request->get('provider');
            $startData = new DateTime($request->get('start'), new DateTimeZone("+3"));
            $endData = new DateTime($request->get('end'), new DateTimeZone("+3"));
        }
        $createData = new DateTime($request->get('end'), new DateTimeZone("+3"));

        $form = $this->createForm(RegistryCommandMainType::class, [
            'provider_name' => $provider,
            'start' => $startData,
            'end' => $endData,
            'create_data' => $createData->modify('-1day'),
        ], [
            'provider_choices' => $providerChoices,
        ]);

        $form->handleRequest($request);

        $provider = $form->get('provider_name')->getData();

        $start = $form->get('start')->getData();
        $end = $form->get('end')->getData();
        $date = $form->get('create_data')->getData();
        $date = $date->format('Y-m-d');

        return $this->render('registry_command_main/main.html.twig', [
            'report_search' => $form->createView(),
            'provider' => $provider,
            'start_data' => $start,
            'end_data' => $end,
            'create_data' => $date
        ]);
    }

    #[Route('/registry_index/{provider}/{start}/{end}', name: 'app_registry_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function show(
        string $provider,
        string $start,
        string $end,
        RegistryCommandRepository $registryCommandRepository,
        BaseHelper $helper
    ): Response
    {
        $res = $registryCommandRepository->findOneBySomeField($provider, $start, $end);

//        dd($provider, $start, $end, $res);
        $dataAll = [];
        $date = $end;
        $utc = 'UTC-3';
        foreach ($res as $record) {
            if ((string)$record->getRegistryConfigId()->getProvider() === $provider) {
                $utc = $record->getRegistryConfigId()->getUtc();
                $dataAll[$record->getOnDate()->format('Y-m-d')]['commands'][$record->getId()] = $record;
            }
        }
        krsort($dataAll, SORT_STRING);

        foreach (array_keys($dataAll) as $date){

            $flag = false;
            $base = $helper->getChargedForProvider($provider, $date, $utc);
            $reg = $registryCommandRepository->findSumTotalCountForProvider($provider, $date);
            if ($base !== $reg){
                $flag = true;
            }
            $dataAll[$date]['flag'] = $flag;
        }

        return $this->render('registry_command_main/index.html.twig', [
            'data' => $dataAll,
            'provider' => $provider,
            'create_data' => $date
        ]);
    }

    #[Route('/registry/logs', name: 'app_registry_logs')]
    #[IsGranted('ROLE_ADMIN')]
    public function log(
        Request $request,
        CollationIssuesLogRepository $collationIssuesLogRepository
    ): Response
    {
        $sDate = new DateTime('NOW', new DateTimeZone("+3"));

        $form = $this->createForm(FilterType::class, [
            'start' => $sDate,
        ]);
        $form->handleRequest($request);
        $dataForm = $form->get('start')->getData();
        $dataForm = $dataForm->format('Y-m-d');

        $data = $collationIssuesLogRepository->findByStarted($dataForm);

        return $this->render('registry_command/logs.html.twig', [
            'filter' => $form->createView(),
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/registry/issues', name: 'app_registry_issues')]
    #[IsGranted('ROLE_ADMIN')]
    public function issues(
        CollationIssuesRepository $collationIssuesRepository
    ): Response
    {
        $data = $collationIssuesRepository->findAll();

        return $this->render('registry_command/issues.html.twig', [
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/registry/issues/save/rejected', name: 'app_registry_issues_save_rejected')]
    #[IsGranted('ROLE_ADMIN')]
    public function saveRejected(
        CollationIssuesRepository $collationIssuesRepository,
        BaseHelper $helper
    ): Response
    {
        $issues = $collationIssuesRepository->findAll();

        foreach ($issues as $issue){
            if ($issue->getType() == 'diff_reg') {
                if ($issue->getStatus() == 'rejected') {
                    $helper->addFixIssueLog($issue);
                    $helper->deleteIssues($issue);
                }
                if ($issue->getStatus() == 'NotFound') {
                    $helper->addFixIssueLog($issue);
                    $helper->deleteIssues($issue);
                }
            }
        }

        return $this->redirectToRoute('app_registry_issues');
    }

    #[Route('/registry/issues/clear', name: 'app_registry_issues_clear', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function clear(
        CollationIssuesRepository $collationIssuesRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $issues = $collationIssuesRepository->findAll();

        foreach ($issues as $issue) {
            $entityManager->remove($issue);
        }
        $entityManager->flush();

        return $this->redirectToRoute('app_registry_issues');
    }


    #[Route('/registry/command/error', name: 'app_registry_command_error')]
    public function showError(
        RegistryErrorLogRepository $registryErrorLogRepository): Response
    {
        $data = $registryErrorLogRepository->findAll();
        $all = 0;
        foreach ($data as $item) {
            $all .= ',' . $item->getId();
        }
        return $this->render('registry_command/error.html.twig', [
            'data' => $data,
            'ids' => $all
        ]);
    }


    #[Route('/registry/command/error/{id}/delete', name: 'app_registry_command_error_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        RegistryErrorLog $user,
        EntityManagerInterface $entityManager,
        RegistryCommandRepository $registryCommandRepository): Response
    {
        $command = $registryCommandRepository->find($user->getCommandId());
        $command->setStatus(RegistryCommandStatusEnum::NEW);
        $entityManager->persist($command);
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_registry_command_error');
    }

    #[Route('/registry/command/error/delete/all/{id}', name: 'app_registry_command_error_delete_all')]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAll(
        Request $request,
        RegistryErrorLogRepository $errorLog,
        EntityManagerInterface $entityManager,
        RegistryCommandRepository $registryCommandRepository): Response
    {
        $ids = $request->get('id');
        $arr[] = explode( ',', $ids);
        foreach ($arr[0] as $key => $val) {
            if ($val <> 0) {
                $log = $errorLog->find((int)$val);
                $command = $registryCommandRepository->find($log->getCommandId());
                $command->setStatus(RegistryCommandStatusEnum::NEW);
                $entityManager->persist($command);
                $entityManager->remove($log);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_registry_pre');
    }


    #[Route('/check/{provider}/{date}/{id}/{bank}', name: 'app_check')]
    #[IsGranted('ROLE_ADMIN')]
    public function checkProvider(int $id, string $provider, string $date, string $bank,
                         Request $request,
                         BaseHelper $helper
    ): Response
    {

        $startData = new DateTime(mb_substr($date, 0, 7) . '-01', new DateTimeZone("+3"));
        $startData = $startData->format('Y-m-d');
        $specData = new DateTime('now', new DateTimeZone("+3"));
        $specData = $specData->format('Y-m-d');

        $form = $this->createForm(ToolsType::class, []);
        $form->handleRequest($request);

        $data = $helper->preVerified($provider, $date, $id, $bank);

        if ( $data['diff_reg'] == 0 && $data['diff_base'] == 0 ) {
            if ($form->getClickedButton() && RegistryCommandStatusEnum::VERIFIED === $form->getClickedButton()->getName()) {
                //Сохранить статус
                $helper->verified($id);
                return $this->redirectToRoute('app_registry_pre', ['provider' => $provider, 'start' => $startData, 'end' => $specData]);
            }
        }

        return $this->render('registry_command/check.html.twig', [
            'report_tools' => $form->createView(),
            'data' => $data,
        ]);
    }


    #[Route('/registry/command/restart/{provider}/{date}/email', name: 'app_registry_command_restart_email')]
    #[IsGranted('ROLE_ADMIN')]
    public function email(string $provider, string $date, UserInterface $user): JsonResponse
    {
        // Проверить почту
        $command = 'registry_get_mail';
        $options = '--form=true';

        $message = $this->runCommandWithLog($command, $provider, $options, $user);

        return $this->json($message);
    }

    #[Route('/registry/command/restart/{date}/create', name: 'app_registry_command_restart_create')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(string $date, UserInterface $user): JsonResponse
    {

        $command = 'registry_create';
        $options = '--form=0';

//        dd($date);
        $message = $this->runCommandWithLog($command, $date, $options, $user);

        return $this->json($message);
    }

    #[Route('/registry/command/restart/{provider}/extract', name: 'app_registry_command_restart_extract')]
    #[IsGranted('ROLE_ADMIN')]
    public function extract(string $provider, UserInterface $user): JsonResponse
    {
        $command = 'registry_extract';
        $options = '--form=true';

        $message = $this->runCommandWithLog($command, $provider, $options, $user);

//        $this->bus->dispatch(new ExtractedRegistryMessage(
//            $provider
//        ));

        return $this->json($message);
    }

    /**
     * @throws ProcessStartFailedException
     * @throws RuntimeException
     * @throws LogicException
     */
    #[Route('/registry/command/restart/{provider}/{date}/check', name: 'app_registry_command_restart_check')]
    #[IsGranted('ROLE_ADMIN')]
    public function check(string $provider, string $date): JsonResponse
    {
        $process = new Process(['/usr/local/bin/php', '../bin/console', 'registry_check', "{$provider}", "{$date}"]);
        $process->start();
        $stderr = [];
        foreach ($process as $data) {
            $stderr[] = $data;
        }

        return $this->json($stderr);
    }

    #[Route('/registry/command/restart/{provider}/fix', name: 'app_registry_command_restart_fix')]
    #[IsGranted('ROLE_ADMIN')]
    public function fix(string $provider, RegistryConfigRepository $registryConfigRepository): JsonResponse
    {
        $activeProviders = $registryConfigRepository->findAllActiveArray();

        if (in_array($provider, $activeProviders, true)) {
            $process = new Process(['/usr/local/bin/php', '../bin/console', 'registry_fix']);
            $process->start();
            $stderr = [];
            foreach ($process as $data) {
                $stderr[] = $data;
            }

            return $this->json($stderr);
        } else {
            return $this->json(['Only provider can fix']);
        }

    }

    /**
     * @throws ProcessStartFailedException
     * @throws LogicException
     * @throws RuntimeException
     */
    #[Route('/registry/command/restart/{provider}/{date}/{process}/diff', name: 'app_registry_command_restart_diff')]
    #[IsGranted('ROLE_ADMIN')]
    public function diff(string $provider, string $date, string $process, UserInterface $user): Response
    {
        $options = '--process='.$process;
        $command = 'registry_diff';

        $this->runCommandProviderWithLog($command, $provider, $date, $options, $user);

        return $this->redirectToRoute('app_registry_pre');

    }

    /**
     * @throws ProcessStartFailedException
     * @throws LogicException
     * @throws RuntimeException
     */
    private function runCommandWithLog($command, $date, $options, UserInterface $user): array
    {
        $process = new Process(['/usr/local/bin/php', '../bin/console', $command, $date, $options]);
        $process->start();
        $message = [];

        foreach ($process as $type => $data) {
            if ($user->getRoot()) {
                if ($process::OUT === $type) {
                    $message[] = $data;
                } else {
                    $message[] = $data;
                }
            } else {
                $message[] = 'ℹ️ Done!';
            }
        }

        return $message;
    }

    /**
     * @throws ProcessStartFailedException
     * @throws LogicException
     * @throws RuntimeException
     */
    private function runCommandProviderWithLog($command, $provider, $date, $options, UserInterface $user): array
    {
        $process = new Process(['/usr/local/bin/php', '../bin/console', $command, $provider, $date, $options]);
        $process->start();
        $message = [];

        foreach ($process as $type => $data) {
            if ($user->getRoot()) {
                if ($process::OUT === $type) {
                    $message[] = $data;
                } else {
                    $message[] = $data;
                }
            } else {
                $message[] = 'ℹ️ Done!';
            }
        }

        return $message;
    }
}
