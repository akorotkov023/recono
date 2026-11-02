<?php

namespace App\Controller;

use App\Form\ChangeStatusType;
use App\Form\ProcessChangeStatusType;
use App\Helper\BaseHelper;
use App\Message\ChangeStatusMessage;
use App\Repository\EmailProcessRepository;
use App\Repository\RegistryCommandRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class UpdateStatusController extends AbstractController
{

    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * @throws OutOfBoundsException
     * @throws RuntimeException
     */
    #[Route('/change/status', name: 'app_change_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function changeStatus(
        Request $request, BaseHelper $helper): Response
    {
        $flag = true;
        $form = $this->createForm(ChangeStatusType::class, []);
        $form->handleRequest($request);

        $count = 0;
        if ($form->getClickedButton() && 'send' === $form->getClickedButton()->getName()) {
            $arr = explode("\n", trim($form->get('ids')->getData()));
            $status = $form->get('status')->getData();
            $is_active = $form->get('is_active')->getData();
            $description = $form->get('description')->getData() ?? null;
//            dd($is_active);
            if(sizeof($arr) > 0) {
                $good = null;
                $diff = null;
                foreach ($arr as $item) {
                    $pos = strpos($item, '-');
                    $txId = str_replace(" ", '', $item);
                    if ($pos) {
                        $txId = preg_replace("/[^a-zA-Z0-9,=\-\s]/", '', $txId);
                        $txId = preg_replace("/^[^\p{L}\d]+/", '', $txId);
                        $txId = preg_replace("/[^\p{L}\d]+$/", '', $txId);

                        $res = $helper->setStatus($txId, $status, $description);
                        if ($res === $status) {
                            if ($is_active){
                                $helper->renotify($txId);
                            }
                            $good[] = $txId;
                            $count++;
                        } else {
                            $diff[] = $txId;
                        }

//                        $this->bus->dispatch(new ChangeStatusMessage(
//                            $form->get('token')->getData(),
//                            $txId,
//                            $status,
//                            $description
//                        ));

                    }
                }
                return $this->render('update_status/status.html.twig', [
                    'change_form' => $form->createView(),
                    'count' => $count,
                    'good' => $good,
                    'diff' => $diff,
                ]);
            }
        }

            return $this->render('update_status/index.html.twig', [
                'change_form' => $form->createView(),
                'count' => $count,
                'flag' => $flag
        ]);
    }

    #[Route('/set/status/{id}/{status}/{description}', name: 'app_set_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function setStatus(
        string $id,
        string $status,
        string $description,
        BaseHelper $helper): Response
    {

        $helper->setStatus($id, $status, $description);
        return $this->redirectToRoute('app_transaction');
    }

    #[Route('/update/status/{id}', name: 'app_update_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(
        string $id,
        BaseHelper $helper): Response
    {
        $helper->updateStatus($id);
        return $this->redirectToRoute('app_transaction');
    }

    #[Route('/change/process/status', name: 'app_change_process_status')]
    #[IsGranted('ROLE_ADMIN')]
    public function changeProcessStatus(
        Request $request,
        EmailProcessRepository $emailProcessRepository,
        RegistryCommandRepository $registryCommandRepository,
        EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProcessChangeStatusType::class);
        $form->handleRequest($request);

        $message = null;
        $success = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $processName = $form->get('process')->getData();
            $processId = $form->get('id')->getData();
            $status = trim($form->get('status')->getData());

            if ($processName === 'registry_command') {
                $process = $registryCommandRepository->find($processId);
            } else {
                $process = $emailProcessRepository->find($processId);
            }

            if (!$process) {
                $message = sprintf('Процесс «%s» с id %d не найден', $processName, $processId);
            } else {
                $oldStatus = $process->getStatus();
                $process->setStatus($status);
                $entityManager->flush();
                $success = true;
                $message = sprintf(
                    'Статус процесса «%s» #%d изменён: «%s» → «%s»',
                    $processName,
                    $process->getId(),
                    $oldStatus,
                    $status
                );
            }
        }

        return $this->render('update_status/process.html.twig', [
            'process_form' => $form->createView(),
            'success' => $success,
            'message' => $message,
        ]);
    }
}
