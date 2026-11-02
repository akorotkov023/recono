<?php

namespace App\Controller;

use App\Helper\BaseHelper;
use DateTime;
use DateTimeZone;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends AbstractController
{
    #[Route('/transaction', name: 'app_transaction')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        BaseHelper $helper
    ): Response
    {

        $sDate = new DateTime('NOW', new DateTimeZone("+3"));
        $startData = $sDate->modify('-7 day');
        $eDate = new DateTime('NOW', new DateTimeZone("+0"));
        $eDate = $eDate->modify('-60 min');

        $txs = $helper->getCreated($startData->format("Y-m-d"), $eDate->format("Y-m-d h:m:s"));

        return $this->render('transaction/index.html.twig', [
            'txs' => $txs,
        ]);
    }
}
