<?php

namespace App\Controller;

use App\Form\SearchToolsType;
use App\Repository\RegistryConfigRepository;
use App\Repository\RegistryTransactionsRepository;
use App\Service\ApiSession;
use App\Service\Connection\Manager;
use App\Service\FinderFiles;
use DateTime;
use DateTimeZone;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class FindController extends AbstractController
{
    public function __construct(
        private string $app,
        private string $in,
        private RegistryConfigRepository $registryConfigRepository,
    )
    {
    }

    #[Route('/find', name: 'app_find')]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_MERCHANT')")]
    public function index(
        Request $request,
        ApiSession $apiSession,
        Manager $singleConnection,
        FinderFiles $finderFiles,
        RegistryTransactionsRepository $transactionsRepository
    ): Response
    {
        $nowData = new DateTime('NOW', new DateTimeZone("+3"));

        $form = $this->createForm(SearchToolsType::class, [
            'selector' => 1,
            'start' => $nowData
        ]);
        $form->handleRequest($request);

        $tx = null;
        $anyId = null;
        $cardId = null;
        $res = null;
        if ($form->getClickedButton() && 'btn_tx_id' === $form->getClickedButton()->getName()) {
            if ($form->get('selector')->getData() == 1) {
                // Поиск в реестре (по provider_id)
                if (strlen($form->get('tx_id')->getData()) > 0) {
                    $res = $transactionsRepository->findProviderId($form->get('tx_id')->getData());

                    if (count($res) > 0) {
                        $tx = $res[0];
                    } else {
                        $tx = null;
                    }
                }
            } elseif ($form->get('selector')->getData() == 2) {
                // Поиск по апи (по id)
                if (strlen($form->get('tx_id')->getData()) > 0) {
                    //Проверка uuid

                    $pos = strpos($form->get('tx_id')->getData(), '-');
                    $txId = str_replace(" ", '', $form->get('tx_id')->getData());
                    if ($pos) {
                        $txId = preg_replace("/[^a-zA-Z0-9,=\-\s]/", '', $txId);
                        $txId = preg_replace("/^[^\p{L}\d]+/", '', $txId);
                        $txId = preg_replace("/[^\p{L}\d]+$/", '', $txId);

                        $res = $apiSession->get('transactions/' . $txId);

                    } else {
                        $res = [];
                    }

                    if (count($res) > 0) {
                        $anyId = $res;
                    } else {
                        $anyId = null;
                    }
                }
            } elseif ($form->get('selector')->getData() == 3) {
                // Поиск по апи (по outertxid)
                if (strlen($form->get('tx_id')->getData()) > 0) {

                    $res = $apiSession->get('transactions?outertxid=' . $form->get('tx_id')->getData());

                    if (count($res) > 0) {
                        $anyId = $res[0];
                    } else {
                        $anyId = null;
                    }
                }
            } elseif ($form->get('selector')->getData() == 4) {
                // Поиск реестра по названию
                if (strlen($form->get('tx_id')->getData()) > 0) {
                    $fileName = $form->get('tx_id')->getData();
                    $res = $finderFiles->getFiles($fileName);
                }
            }
        }

        // Поиск по карте в базе
        if ($form->getClickedButton() && 'btn_any_card' === $form->getClickedButton()->getName()) {
            if (strlen($form->get('any_card')->getData()) > 0) {
                //Запрос для
                $card = $form->get('any_card')->getData();
                $date = $form->get('start')->getData();
                $providers = $this->registryConfigRepository->findAllActiveArray();
                $providerList = "'" . implode("', '", $providers) . "'";
                //С этой даты поменялся формат
                $border = new DateTime('2023-07-03', new DateTimeZone("+3"));
                if ($date > $border) {
                    $sql = /** @lang PostgreSQL */ "
                select
                    amount as amount,
                    LEFT(money_storage->>'cardnumber',6) || '******' || RIGHT(money_storage->>'cardnumber',4)  as cardnumber,
                    coalesce(money_storage->>'cardholder', recipient) as cardholder,
                    id, provider, outertxid  as providertxid,
                    rrn, started, status, txid, type
                from txs where money_storage_meta::jsonb->>'masked_cardnumber' like '{$card}' 
                and provider in ($providerList)
                and started >= date ('{$date->format("Y-m-d")}') at time zone 'utc' at time zone 'UTC-3' and
                started < date ('{$date->format("Y-m-d")}') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
                ";
                } else {
                    $sql = /** @lang PostgreSQL */ "
                select
                    amount as amount,
                    LEFT(money_storage->>'cardnumber',6) || '******' || RIGHT(money_storage->>'cardnumber',4)  as cardnumber,
                    coalesce(money_storage->>'cardholder', recipient) as cardholder,
                    id, provider, outertxid  as providertxid,
                    rrn, started, status, txid, type
                from txs where money_storage->>'cardnumber' like '{$card}' 
                and provider in ($providerList)
                and started >= date ('{$date->format("Y-m-d")}') at time zone 'utc' at time zone 'UTC-3' and
                started < date ('{$date->format("Y-m-d")}') at time zone 'utc' at time zone 'UTC-3' + interval '1 day'
                ";
                }
                $conn = $singleConnection->getConnection($this->app);
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $cardId = $stmt->fetchAll();

                if (count($cardId) == 0) {
                    $cardId = null;
                }
            }
        }

        $osh = "";

        return $this->render('find/index.html.twig', [
            'data' => $tx,
            'filter' => $form->createView(),
            'any' => $anyId,
            'card' => $cardId,
            'osh' => $osh,
            'res' => $res
        ]);
    }

    #[Route('/find_file/{file}', name: 'app_find_file')]
    #[IsGranted('ROLE_ADMIN')]
    public function getFile(
        Request $request,
        FinderFiles $finderFiles
    ): StreamedResponse
    {
        $fileName = $request->get('file');
        $filePath = $finderFiles->getPath($fileName);

        if (file_exists($filePath)) {
            $streamedResponse = new StreamedResponse(function () use ($filePath): void {
                // Open the file in read mode
                $fileStream = fopen($filePath, 'r');

                // Output the file content in chunks
                while (!feof($fileStream)) {
                    echo fread($fileStream, 1024); // Adjust chunk size as needed
                    flush();
                }

                fclose($fileStream);
            });

            // Set appropriate headers for file download
            $streamedResponse->headers->set('Content-Type', 'application/octet-stream');
            $streamedResponse->headers->set('Content-Disposition', 'attachment; filename="'.$fileName.'"');

            return $streamedResponse->send();
        }
    }

}
