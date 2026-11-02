<?php


namespace App\Controller;

use App\Entity\BalanceConfig;
use App\Form\BalanceConfigType;
use App\Helper\BaseHelper;
use App\Repository\BalanceConfigRepository;
use App\Service\ApiSession;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BalanceConfigController extends AbstractController
{
    protected ApiSession $api;

    public function __construct(ApiSession $api) {
        $this->api = $api;
    }

    #[Route('/balance/config', name: 'app_balance_config')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(BalanceConfigRepository $balanceConfigRepository): Response
    {
        $settings = $balanceConfigRepository->findAllAndSort();
        return $this->render('balance_config/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/balance/config/create', name: 'app_balance_config_create')]
    #[Route('/balance/config/{id}/edit', name: 'app_balance_config_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        BaseHelper $helper,
        BalanceConfig $user = null
    ): Response
    {
        if(null === $user) {
            $user = new BalanceConfig();
        }
        $partners = $this->api->get('profile');
        $data = [];
        foreach ($partners as $partner) {
            $data[$partner['name']] = $partner['id'];
        }
        $provider_name = $helper->getRequestApi('GET', 'wallet?type=provider');
        foreach ($provider_name as $key => $value) {
            if ($value->env === 'prod') {
                $data[$value->name] = $value->id + 1000;
            }
        }
        $form = $this->createForm(BalanceConfigType::class, $user, [
            'help_attr' => $data,
            'user_exists' => null !== $user->getId()
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $user->setPartnerId($form->get('partner_id')->getData());
            $user->setPartnerName($form->get('partner_name')->getData());
            $user->setWalletId($form->get('wallet_id')->getData());
            $user->setType($form->get('type')->getData());
            $user->setProvider($form->get('provider')->getData());
            $user->setDescription($form->get('description')->getData());
            $user->setIsActive($form->get('is_active')->getData());

            $entityManager->persist($user);
            $entityManager->flush();
            return $this->redirectToRoute('app_balance_config');
        }

        return $this->render('balance_config/edit.html.twig', [
            'user_form' => $form->createView(),
            'user_exists' => null !== $user->getId(),
            'errors' => $form->getErrors()
        ]);
    }

    #[Route('/balance/config/{id}/delete', name: 'app_balance_config_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(BalanceConfig $user, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();
        return $this->redirectToRoute('app_balance_config');
    }
}
