<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ErrorController extends AbstractController
{
    #[Route('/error', name: 'app_error')]
    public function show(Request $request): Response
    {
        return $this->render('error/index.html.twig', [
            'controller_name' => 'ErrorController',
            'message' => $request->attributes->get('exception')->getMessage()
        ]);
    }
}
