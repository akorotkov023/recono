<?php


namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class BackendController extends AbstractController
{

    #[Route('/backend', name: 'app_backend')]
    public function index(UserInterface $user): RedirectResponse
    {
        if ($user->getRoles() == 'ROLE_ADMIN'){
            return $this->redirect('/registry_pre');
        }
        if ($user->getRoles() == 'ROLE_USER'){
            return $this->redirect('/find');
        }
        return $this->redirect('/registry_pre');
    }

    #[Route('/', name: 'app_main')]
    public function indexMain(): RedirectResponse
    {
        return $this->redirect('/login');
    }

}
