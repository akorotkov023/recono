<?php

namespace App\Controller;

use App\Form\ChangeDateType;
use App\Helper\BaseHelper;
use App\Service\ApiSession;
use DateTime;
use DateTimeImmutable;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChangeDateController extends AbstractController
{
    #[Route('/change/date', name: 'app_change_date')]
    #[IsGranted('ROLE_ADMIN')]
    public function changeDate(
        Request $request,
        BaseHelper $helper,
        ApiSession $apiSession): Response
    {
        $form = $this->createForm(ChangeDateType::class, []);
        $form->handleRequest($request);

        $json = null;
        $message = null;
        $success = null;

        $clicked = $form->getClickedButton();
        if ($clicked) {
            $id = $form->get('id')->getData();
            $started = $form->get('started')->getData();

            if ('find' === $clicked->getName()) {
                $res = $this->findByApi($apiSession, $id);
                if (!empty($res)) {
                    $json = json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $message = 'Транзакция не найдена';
                    $success = false;
                }
            } elseif ('change' === $clicked->getName()) {
                if (empty($id) || empty($started)) {
                    $message = 'Заполните id и дату started';
                    $success = false;
                } else {
                    $res = $this->findByApi($apiSession, $id);
                    if (empty($res)) {
                        $message = 'Транзакция не найдена';
                        $success = false;
                    } else {
                        if ($started instanceof DateTimeImmutable) {
                            $started = DateTime::createFromImmutable($started);
                        }
                        $helper->updateStartedToUpdated($id, $started);
                        $message = sprintf('Дата started обновлена для транзакции %s', $id);
                        $success = true;
                    }
                }
            }
        }

        return $this->render('change_date/index.html.twig', [
            'change_date_form' => $form->createView(),
            'json' => $json,
            'message' => $message,
            'success' => $success,
        ]);
    }

    private function findByApi(ApiSession $apiSession, $id): array
    {
        if (strlen((string) $id) === 0) {
            return [];
        }

        $pos = strpos((string) $id, '-');
        $txId = str_replace(" ", '', (string) $id);
        if ($pos) {
            $txId = preg_replace("/[^a-zA-Z0-9,=\-\s]/", '', $txId);
            $txId = preg_replace("/^[^\p{L}\d]+/", '', $txId);
            $txId = preg_replace("/[^\p{L}\d]+$/", '', $txId);

            $res = $apiSession->get('transactions/' . $txId);

            if (isset($res['error']) || isset($res['code']) || !isset($res['id'])) {
                return [];
            }

            return $res;
        }

        return [];
    }
}
