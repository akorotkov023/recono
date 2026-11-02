<?php

namespace App\Controller;

use App\Form\Main\PartnershipInputType;
use App\Form\TelegramFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TelegramController extends AbstractController
{
    #[Route('/telegram', name: 'app_telegram')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(TelegramFormType::class, []);
        $form->handleRequest($request);

        return $this->render('telegram/index.html.twig', [
            'telegram_form' => $form->createView(),
        ]);
    }
}
