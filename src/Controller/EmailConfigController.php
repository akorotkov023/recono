<?php

namespace App\Controller;

use App\Entity\EmailConfig;
use App\Form\EmailConfigType;
use App\Helper\BaseHelper;
use App\Repository\EmailConfigRepository;
use App\Repository\RegistryConfigRepository;
use App\Service\ApiSession;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Annotation\Route;

class EmailConfigController extends AbstractController
{
    protected ApiSession $api;

    public function __construct(ApiSession $api) {
        $this->api = $api;
    }

    #[Route('/email/config', name: 'app_email_config')]
    public function index(EmailConfigRepository $emailConfigRepository): Response
    {
        $settings = $emailConfigRepository->findAllSort();
        return $this->render('email_config/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/email/config/create', name: 'app_email_config_create')]
    #[Route('/email/config/{id}/edit', name: 'app_email_config_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailConfig $user = null,
        BaseHelper $baseHelper,
        NotifierInterface $notifier,
        RegistryConfigRepository $registryConfigRepository
    ): Response
    {
        if(null === $user) {
            $user = new EmailConfig();
            $user->setType([
                'payment' => 'payment',
                'payout' => 'payout'
            ]);
        }

        $partners = $this->api->get('profile');
        $data = [];
        foreach ($partners as $partner) {
            $data[$partner['id']. " - " . $partner['name']] = $partner['id'];
        }

        $providers = $registryConfigRepository->findAllActiveArray();
        $providerData = [];
        foreach ($providers as $provider) {
            $providerData[$provider] = $provider;
        }

        $form = $this->createForm(EmailConfigType::class, $user, [
            'help_attr' => $data,
            'provider' => $providerData,
            'user_exists' => null !== $user->getId()
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user->setCreatedAt(new DateTime('NOW', new DateTimeZone("+3")));
            if (sizeof($form->get('type')->getData()) === 0) {
                $notifier->send(new Notification("Не выбран Тип реестра!", ['browser']));
            } else {
                $res = $baseHelper->validateEmailArray(explode(", ",$form->get('email_to')->getData()[0]));
                if (isset($res)) {
                    $entityManager->persist($user);
                    $entityManager->flush();
                    return $this->redirectToRoute('app_email_config');
                } else {
                    $notifier->send(new Notification("Неверное поле Кому! Образец: register@example.com", ['browser']));
                }
            }
        }

        return $this->render('email_config/edit.html.twig', [
            'user_form' => $form->createView(),
            'user_exists' => null !== $user->getId(),
            'errors' => $form->getErrors(),
        ]);
    }

    #[Route('/email/config/{id}/delete', name: 'app_email_config_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(EmailConfig $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_email_config');
    }
}
