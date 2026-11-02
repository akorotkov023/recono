<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BeginController extends AbstractController
{
    #[Route('/begin', name: 'app_begin')]
    public function index(): Response
    {
        return $this->render('begin/logs.html.twig', [
            'controller_name' => 'BeginController',
        ]);
    }
}
