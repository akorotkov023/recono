<?php


namespace App\Controller;


use App\Entity\RegistryConfig;
use App\Form\RegistryConfigType;
use App\Helper\BaseHelper;
use App\Repository\RegistryConfigRepository;
use App\Service\ApiSession;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegistryConfigController extends AbstractController
{
    protected ApiSession $api;

    public function __construct(ApiSession $api) {
        $this->api = $api;
    }

    #[Route('/registry/config', name: 'app_registry_config')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(RegistryConfigRepository $providerConfigRepository): Response
    {
        $settings = $providerConfigRepository->findAllAndSort();
        return $this->render('registry_config/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/registry/config/create', name: 'app_registry_config_create')]
    #[Route('/registry/config/{id}/edit', name: 'app_registry_config_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        BaseHelper $helper,
        RegistryConfig $user = null
    ): Response
    {
        if(null === $user) {
            $user = new RegistryConfig();
        }
        $data = [];
        $data['name'] = [];
        $data['provider'] = [];

        // Провайдеры — из API процессинга /api/v1/providers
        $providers = $helper->getRequestApi('GET', 'providers');
        foreach ($providers->providers ?? [] as $providerName) {
            $data['provider'][$providerName] = $providerName;
        }

        // Кошельки провайдера (provider_wallet_id) — из /api/v1/wallet?type=provider
        $provider_name = $helper->getRequestApi('GET', 'wallet?type=provider');
        foreach ($provider_name as $key => $value) {
            $data['name'][$value->name] = $value->id;
        }

        $form = $this->createForm(RegistryConfigType::class, $user, [
            'help_attr' => $data,
            'user_exists' => null !== $user->getId()
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user->setProvider($form->get('provider')->getData());
            $user->setSlug($form->get('slug')->getData());
            $user->setProviderWalletId((int) $form->get('providerWalletId')->getData());
            $user->setDescription($form->get('description')->getData());
            $user->setIsActive($form->get('is_active')->getData());
            $user->setCreatedAt(new DateTime('NOW', new DateTimeZone("+3")));

            $entityManager->persist($user);
            $entityManager->flush();
            return $this->redirectToRoute('app_registry_config');
        }

        return $this->render('registry_config/edit.html.twig', [
            'user_form' => $form->createView(),
            'user_exists' => null !== $user->getId(),
            'errors' => $form->getErrors()
        ]);
    }

    #[Route('/registry/config/{id}/delete', name: 'app_registry_config_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(RegistryConfig $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_registry_config');
    }

}
