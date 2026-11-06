<?php


namespace App\Service;


use App\Helper\BaseHelper;
use NumberToWords\NumberToWords;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Генератор реестров csv и xlsx
 */
class RegistryFileGenerator
{
    protected ApiSession $api;
    protected BaseHelper $helper;

    public function __construct( protected string $app_registry_dir, protected string $app_act_dir, ApiSession $api, BaseHelper $helper)
    {
        $this->api = $api;
        $this->helper = $helper;

        foreach ([$this->app_registry_dir, $this->app_act_dir] as $dir) {
            if ($dir !== '' && !is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }
    }

    public function exportToFileDefault($format, $type, $title, $transactions, $on_date): string|null
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $fileName = $title.'_'.$type.'_'.$on_date.'.'.$format;
        $sheet->setTitle($title);

        // Добавление шапки
        $header = ["id","txid","amount","fee","status","description","type","started","sender"];
        $sheet->fromArray($header); // A1 start

        if ($format === 'csv') {
            $row = 2; // Данные вставлять со 2-й строки
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }
            $a = ['A','B','C','D','E','F','G','H','I'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }
            // Write and send created spreadsheet
            $writer = new Csv($spreadsheet);

        } else {
            //xlsx
            // Стили документа по умолчанию
            $spreadsheet->getDefaultStyle()
                ->getFont()
                ->setName('Liberation Sans')
                ->setSize(9);

            // Установка стилей для шапки
            $headerStyles = [
                'font'=>[
                    'color'=>[
                        'rgb' => '000'
                    ],
                    'bold' => true
                ],
                'fill'=>[
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'B9BADB'
                    ]
                ],
            ];
            $spreadsheet->getActiveSheet()->getStyle('A1:I1')->applyFromArray($headerStyles);

            // Данные вставлять со 2-й строки
            $row = 2;
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }

            $a = ['A','B','C','D','E','F','G','H','I'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }

            // Write and send created spreadsheet
            $writer = new Xlsx($spreadsheet);
        }

        // Сохранение файла
        try {
            $writer->save($this->app_registry_dir.$fileName);
        } catch (Exception $e) {
            return null;
        }

        return $fileName;

    }

    public function exportToFileMerchantDiff($format, $title, $transactions, $on_date): string|null
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $fileName = $title.'_diff_'.$on_date.'.'.$format;
        $sheet->setTitle($title);

        // Добавление шапки "Дата и время",Сумма,Отправитель,Получатель,"ID операции"
        $header = ["Дата и время","Сумма","Отправитель","Получатель","ID операции"];
        $sheet->fromArray($header); // A1 start

        if ($format === 'csv') {
            $row = 2; // Данные вставлять со 2-й строки
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }
            $a = ['A','B','C','D','E'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }
            // Write and send created spreadsheet
            $writer = new Csv($spreadsheet);

        } else {
            //xlsx
            // Стили документа по умолчанию
            $spreadsheet->getDefaultStyle()
                ->getFont()
                ->setName('Liberation Sans')
                ->setSize(9);

            // Установка стилей для шапки
            $headerStyles = [
                'font'=>[
                    'color'=>[
                        'rgb' => '000'
                    ],
                    'bold' => true
                ],
                'fill'=>[
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'B9BADB'
                    ]
                ],
            ];
            $spreadsheet->getActiveSheet()->getStyle('A1:E1')->applyFromArray($headerStyles);

            // Данные вставлять со 2-й строки
            $row = 2;
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }

            $a = ['A','B','C','D','E'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }

            // Write and send created spreadsheet
            $writer = new Xlsx($spreadsheet);
        }

        // Сохранение файла
        try {
            $writer->save($this->app_registry_dir.$fileName);
        } catch (Exception $e) {
            return null;
        }

        return $fileName;

    }

    public function exportToFile($format, $type, $title, $transactions, $on_date): string|null
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $fileName = $title.'_'.$type.'_'.$on_date.'.'.$format;
        $sheet->setTitle($title);

        // Добавление шапки
        $header = ["id","txid","amount","enrollment","status","description","type","started","name"];
        $sheet->fromArray($header); // A1 start

        if ($format === 'csv') {
            $row = 2; // Данные вставлять со 2-й строки
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }
            $a = ['A','B','C','D','E','F','G','H','I'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }
            // Write and send created spreadsheet
            $writer = new Csv($spreadsheet);

        } else {
            //xlsx
            // Стили документа по умолчанию
            $spreadsheet->getDefaultStyle()
                ->getFont()
                ->setName('Liberation Sans')
                ->setSize(9);

            // Установка стилей для шапки
            $headerStyles = [
                'font'=>[
                    'color'=>[
                        'rgb' => '000'
                    ],
                    'bold' => true
                ],
                'fill'=>[
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'B9BADB'
                    ]
                ],
            ];
            $spreadsheet->getActiveSheet()->getStyle('A1:I1')->applyFromArray($headerStyles);

            // Данные вставлять со 2-й строки
            $row = 2;
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
//                $cell = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(3, $row);
//                $cell->setValueExplicit($cell->getValue(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
//                $cell = $spreadsheet->getActiveSheet()->getCellByColumnAndRow(9, $row);
//                $cell->setValueExplicit($cell->getValue(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $row++;
            }

            $a = ['A','B','C','D','E','F','G','H','I'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }

            //Баланс на 2024-05-24 00:00:00:;name:;balance:;;;;;;
//            $basement = ["Баланс на ". $on_date, "name","balance","","","","","",""];
//            $sheet->fromArray($basement, null, 'A'.$row); // шапка подвала с балансами
//            $row++;
//
//            foreach ($balances as $id => $balance) {
//                $partner_login = $this->helper->getLoginById($id);
//                $basement = ["",$partner_login,$balance,"","","","","",""];
//                $sheet->fromArray($basement, null, 'A'.$row); // балансы
//                $row++;
//            }

            // Write and send created spreadsheet
            $writer = new Xlsx($spreadsheet);
        }

        // Сохранение файла
        try {
            $writer->save($this->app_registry_dir.$fileName);
        } catch (Exception $e) {
            return null;
        }

        return $fileName;

    }


    public function exportToFileBR($format, $type, $title, $transactions, $on_date): string|null
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $fileName = $title.'_'.$type.'_'.$on_date.'_0'.'.'.$format;
        $sheet->setTitle($title);

        // Добавление шапки
        $header = ["id","amount","amount_transfer","fee","started","partner","currency","t_id"];
        $sheet->fromArray($header); // A1 start

        if ($format === 'csv') {
            $row = 2; // Данные вставлять со 2-й строки
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }
            $a = ['A','B','C','D','E','F','G','H'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }

            // Write and send created spreadsheet
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(';');

        } else {
            //xlsx
            // Стили документа по умолчанию
            $spreadsheet->getDefaultStyle()
                ->getFont()
                ->setName('Liberation Sans')
                ->setSize(9);

            // Установка стилей для шапки
            $headerStyles = [
                'font'=>[
                    'color'=>[
                        'rgb' => '000'
                    ],
                    'bold' => true
                ],
                'fill'=>[
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'B9BADB'
                    ]
                ],
            ];
            $spreadsheet->getActiveSheet()->getStyle('A1:H1')->applyFromArray($headerStyles);

            // Данные вставлять со 2-й строки
            $row = 2;
            foreach ($transactions as $txs) {
                $sheet->fromArray($txs, null, 'A'.$row); // A2 start
                $row++;
            }

            $a = ['A','B','C','D','E','F','G','H'];
            foreach ($a as $item) {
                $spreadsheet->getActiveSheet()->getColumnDimension($item)->setAutoSize(true);
            }

            // Write and send created spreadsheet
            $writer = new Xlsx($spreadsheet);
        }

        // Сохранение файла
        try {
            $writer->save($this->app_registry_dir.$fileName);
        } catch (Exception $e) {
            return null;
        }

        return $fileName;

    }


    public function exportActToFile(string $title, $d, $type, $date): string|null
    {

        $rub = $d['sum'];
        $kopecks = '00';
        $fee = $d['fee'];
        $fee_kop = '00';

        if ($d['sum'] <> 0) {
            $rub = mb_substr($d['sum'], 0, -2) . '00';
            $kopecks = mb_substr($d['sum'], -2);
        }
        if ($d['fee'] <> 0) {
            $fee = mb_substr($d['fee'], 0, -2) . '00';
            $fee_kop = mb_substr($d['fee'], -2);
        }

        $fileName = "Act_". $type .".xlsx";


        $end = date("t.m.Y", strtotime($date));
        // Загружаем акт

        $spreadsheet = IOFactory::load(dirname(__DIR__, 2)."/public/uploads/$fileName");

        $worksheet = $spreadsheet->getActiveSheet();

//            $worksheet->getCell('F6')->setValue("«{$date['day']}» {$date['month']} {$date['year']} г.");
        $worksheet->getCell('F6')->setValue("$end г.");
        $worksheet->getCell('A7')->setValue("   ________________________________, именуемое в дальнейшем «Исполнитель», в лице _________________, действующего на основании 
________________, с одной стороны, и ________________________, именуемое  в дальнейшем «Заказчик», в лице _______________________, 
действующего на основании __________________, с другой стороны, далее совместно именуемые «Стороны», а по отдельности – «Сторона», 
подписали настоящий Акт об оказанных услугах по Договору №____ от __________ г. за Отчетный период с  $date г. по $end г. 
(далее – Акт) о нижеследующем:");

        $worksheet->getCell('A8')->setValue("   Исполнитель в Отчетном месяце с $date г. по $end г.  оказал Заказчику услуги, предусмотренные Договором №____ от __________ г. (далее – Договор), надлежащим образом.");

        $worksheet->getCell('D10')->setValue($date);
        $worksheet->getCell('D11')->setValue($end);

        $worksheet->getCell('D14')->setValue($d['sum']/100);
        $worksheet->getCell('D15')->setValue($d['fee']/100);

        $numberToWords = new NumberToWords();
        $currencyTransformer = $numberToWords->getCurrencyTransformer('ru');

        $rub_words = $currencyTransformer->toWords($rub, 'RUB');
        $rub_words = mb_strtoupper(mb_substr($rub_words, 0, 1), 'UTF-8') . mb_substr($rub_words, 1);

        $fee_words = $currencyTransformer->toWords($fee, 'RUB');
        $fee_words = mb_strtoupper(mb_substr($fee_words, 0, 1), 'UTF-8') . mb_substr($fee_words, 1);

        $worksheet->getCell('E14')->setValue($rub_words . ' ' . $kopecks . ' копеек');
        $worksheet->getCell('E15')->setValue($fee_words . ' ' . $fee_kop . ' копеек');

        // Write and send created spreadsheet
        $writer = new Xlsx($spreadsheet);

        // Сохранение файла
        try {
            $writer->save($this->app_act_dir.$title);
        } catch (Exception $e) {
            return null;
        }

        return $title;

    }
}
