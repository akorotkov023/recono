<?php

namespace App\Controller;

use App\Form\FilterType;
use App\Helper\BaseHelper;
use App\Repository\RegistryCommandRepository;
use App\Repository\RegistryConfigRepository;
use DateTime;
use DateTimeZone;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InfoController extends AbstractController
{
    public function __construct(private string $app)
    {}

    #[Route('/info_pre', name: 'app_info_pre')]
    #[IsGranted('ROLE_ADMIN')]
    public function info(
        Request $request,
    ): Response
    {
        $sDate = new DateTime('NOW', new DateTimeZone("+3"));
        $sDate->modify('-1 day');

        $form = $this->createForm(FilterType::class, [
            'start' => $sDate,
        ]);
        $form->handleRequest($request);

        return $this->render('info/info.html.twig', [
            'controller_name' => 'InfoController',
            'filter' => $form->createView(),
        ]);
    }

    #[Route('/info/{value}', name: 'app_info')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request,
        BaseHelper $helper,
        RegistryCommandRepository $registryCommandRepository,
        RegistryConfigRepository $configRepository
    ): Response
    {
        $dateRequest = $request->get('value');
        $data = $helper->getInfo($dateRequest);
        $providers = $helper->getCharged($dateRequest);
        $base = [];

        foreach ($providers as $item){
            $provider = $item['provider'];
            $config = $configRepository->findOneBy(['provider' => $item['provider']]);
            $utc = $config?->getUtc() ?? 'UTC-3';
            $base[] = $helper->getChargedWithProvider($dateRequest, $provider, $utc);
        }
        $reg = $registryCommandRepository->findSumTotalCount($dateRequest);
        foreach ($base as $b) {
            $provider = $b['provider'];
            $exists = false;
            foreach ($reg as $r) {
                if ($r['provider'] === $provider) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $reg[] = [
                    'provider' => $provider,
                    'count' => 0
                ];
            }
        }
        $counts['base'] = $base;
        $counts['reg'] = $reg;

        return $this->render('info/index.html.twig', [
            'controller_name' => 'InfoController',
            'data' => $data,
            'base_reg_counts' => $counts,
            'datetime' => $request->get('value')
        ]);
    }

    #[Route('/health', name: 'app_health')]
    public function health(BaseHelper $helper): JsonResponse
    {
        $sDate = new DateTime('NOW', new DateTimeZone("+3"));

        return new JsonResponse([
            'appversion' => '0.0.5',
            'core_status' => $helper->getCheckProcessingBase(),
            'db_status' => $helper->getCheckBase(),
            'domain' => "example.com",
            'env' => $this->app,
            "uptime" => $sDate->format('Y-m-d h:i:s'),
            "utc_start_time" => $sDate->format('Y-m-d h:i:s'),
        ]);
    }
}

