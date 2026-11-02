<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_backend');
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/error', name: 'app_error')]
    public function error(Request $request): Response
    {

        return $this->render('security/error.html.twig', [
            'error' => $request->get('code') . " -> " . $request->get('error')
        ]);
    }

    #[NoReturn] #[Route(path: '/root/{flag}', name: 'app_root')]
    public function root(Request $request, UserInterface $user, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($request->get('flag') === 'false') {
            $user->setRoot(false);
        } else {
            $user->setRoot(true);
        }
        $entityManager->persist($user);
        $entityManager->flush();


        return $this->redirect($request->headers->get('Referer'));
    }
}
