<?php

namespace App\Controller;

use App\Form\ActSearchType;
use App\Form\RegistryConfigType;
use App\Form\RegistrySearchType;
use App\Helper\BaseHelper;
use App\Enum\RegistryTypeEnum;
use App\Repository\EmailConfigRepository;
use App\Service\ApiSession;
use App\Service\RegistryFileGenerator;
use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Money\Currency;
use Money\Money;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class MonthController extends AbstractController
{

    protected ApiSession $api;
    private RegistryFileGenerator $fileGenerator;

    public function __construct(ApiSession $api, RegistryFileGenerator $fileGenerator) {
        $this->api = $api;
        $this->fileGenerator = $fileGenerator;
    }

    #[Route('/act', name: 'app_act')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        Request $request
    ): Response
    {
        $partners = $this->api->get('profile');
        $data = [];
        foreach ($partners as $partner) {
            $data['partners'][$partner['name']] = $partner['id'];
        }

        $provider_name = $this->api->get('wallet?type=provider');
        foreach ($provider_name['data'] as $key => $value) {
            $data['providers'][$value['provider']] = $value['provider'];
        }

        try{
            $nowData = new DateTime('NOW', new DateTimeZone("+3"));
            $nowData->modify('-1 month');

            $form = $this->createForm(ActSearchType::class, [
                'type' => 'payment',
                'month' => $nowData,
            ], [
                'help_attr' => $data
            ]);
            $form->handleRequest($request);

            $flag = false;

            return $this->render('act/index.html.twig', [
                'search' => $form->createView(),
                'flag' => $flag
            ]);
        } catch (\Exception $e){}
        return $this->redirectToRoute('app_error', ['code' => 503, 'error' => 'SQLSTATE[08006_1] Ошибка соединения с базой']);
    }


    #[Route('/act/{month}/{type}/{name}/{id}/{ids}/generate', name: 'app_act_generate')]
    #[IsGranted('ROLE_ADMIN')]
    public function generate(
        string $month,
        string $type,
        string $name,
        string $id,
        string $ids,
        BaseHelper $helper,
        UrlGeneratorInterface $urlGenerator
    ): Response
    {
        $month = DateTime::createFromFormat('m-Y', $month);
        $month = new DateTime($month->format('Y-m' . '-01'), new DateTimeZone("+3"));

        $data['month'] = $month;
        $data['type'] = $type;
        $data['partner_id'] = $id;
        $data['ids'] = $ids;
        $fileName = '';

        try {
            $result = $helper->getActData($data, $id);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if ($result <> null) {

            $date = $month->format('d.m.Y');
            $res[0]['sum'] = $result->sum ? $result->sum : 0;
            $res[0]['fee'] = $result->fee ? $result->fee : 0;

            //Формируем акт
            $fileName = $this->fileGenerator->exportActToFile(
                'Act_'.$name."_".$type.'_'.$month->format('mY').'.xlsx',
                $res[0],
                $type,
                $date
            );

            if (isset($fileName)) {

                return $this->render('act/act.html.twig', [
                    'link' => $fileName
                ]);

            } else {
                return new RedirectResponse($urlGenerator->generate('app_error', ['code' => 0, 'error' => 'Акт не создан']));
            }

        }

        return $this->render('act/act.html.twig', [
            'link' => $fileName
        ]);
    }

    #[Route('/month/registry', name: 'app_month_registry')]
    #[IsGranted('ROLE_ADMIN')]
    public function getRegistry(
        Request $request,
        EmailConfigRepository $emailConfigRepository
    ): Response
    {
        $partners = $emailConfigRepository->findAllSortByActive();

        try{
            $nowData = new DateTime('NOW', new DateTimeZone("+3"));
            $nowData->modify('-1 month');

            $form = $this->createForm(RegistrySearchType::class, [
                'type' => 'csv',
                'status' => 'charged',
                'month' => $nowData,
            ], [
                'help_attr' => $partners
            ]);
            $form->handleRequest($request);

            $flag = false;

            return $this->render('month_registry/index.html.twig', [
                'search' => $form->createView(),
                'flag' => $flag
            ]);
        } catch (\Exception $e){}
        return $this->redirectToRoute('app_error', ['code' => 503, 'error' => 'SQLSTATE[08006_1] Ошибка соединения с базой']);
    }

    #[Route('/registry/{month}/{format}/{slug}/{status}/generate', name: 'app_month_registry_generate')]
    #[IsGranted('ROLE_ADMIN')]
    public function generateMonthRegistry(
        string $month,
        string $format,
        string $slug,
        string $status,
        BaseHelper $helper,
        UrlGeneratorInterface $urlGenerator,
        EmailConfigRepository $emailConfigRepository
    ): Response
    {
        $monthDate = DateTime::createFromFormat('m-Y', $month);

        $start = new DateTime($monthDate->format('Y-m-01'), new DateTimeZone('+3'));
        $start = $start->format('Y-m-d');

        $end = new DateTime($monthDate->format('Y-m-01'), new DateTimeZone('+3'));
        $end->modify('+1 month');
        $end = $end->format('Y-m-d');

        $partner = $emailConfigRepository->findOneBy(["slug" => $slug]);
        $terminalsId = implode(', ', $partner->getTerminal());

        try {
            if ($format !== '' && $month && $slug) {
                $transactions = $helper->getMonthDataForMerchantRegistryPercent($start, $end, $terminalsId, $status);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if (sizeof($transactions) !== 0) {

            if ($partner->getRegistryType() === RegistryTypeEnum::PERCENT) {
                foreach ($transactions as $key => $val) {
                    $amount = new Money((int)$val['amount'], new Currency('RUB'));

                    // считаем 0.4% от суммы
                    $fee = $amount->multiply('0.004', Money::ROUND_HALF_UP);

                    $transactions[$key]['amount'] = number_format($amount->getAmount() / 100, 2, '.', '');
                    $transactions[$key]['fee'] = number_format($fee->getAmount() / 100, 2, '.', '');
                }
            } else {
                foreach ($transactions as $key => $val) {
                    $amount = new Money((int)$val['amount'], new Currency('RUB'));
                    $fee = new Money((int)$val['fee'], new Currency('RUB'));

                    $transactions[$key]['amount'] = number_format($amount->getAmount() / 100, 2, '.', '');
                    $transactions[$key]['fee'] = number_format($fee->getAmount() / 100, 2, '.', '');
                }
            }

            //Формируем реестр
            $fileName = $this->fileGenerator->exportToFileDefault($format, 'month_'.$status, $slug, $transactions, $month);

            if (isset($fileName)) {
                return $this->render('month_registry/registry.html.twig', [
                    'link' => $fileName
                ]);
            } else {
                return new RedirectResponse($urlGenerator->generate('app_error', ['code' => 0, 'error' => 'Реестр не создан']));
            }
        } else {
            //Если нет данных то формируем пустой реестр
            $fileName = $this->fileGenerator->exportToFileDefault($format, 'month', $slug, [], $month);

            if (isset($fileName)) {
                return $this->render('month_registry/registry.html.twig', [
                    'link' => $fileName
                ]);
            } else {
                return new RedirectResponse($urlGenerator->generate('app_error', ['code' => 0, 'error' => 'Реестр не создан']));
            }
        }
    }
}
