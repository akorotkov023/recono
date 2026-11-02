<?php


namespace App\Service;


use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RegistryFileSender
{

    public function __construct(
        protected string $app_registry_dir,
        private MailerInterface $mailer,
        private string $mailer_from,
        private LoggerInterface $logger,
    ){}

    /**
     * Отправка одного или двух файлов в одном письме
     *
     * @param array $files
     * @param array $to
     * @param array $cc
     * @return string
     */
    public function sendFile(array $files, array $to, array $cc, string $on_date, string $slug = '', array $amounts = [], array $counts = []): string
    {
        $filePath = $this->app_registry_dir;
        $registry = [];
        $allTo = [];
//        $allCc = [];

        if (sizeof($files) > 1 and sizeof($to) > 1) {
            foreach (array_diff($files, array('')) as $key => $file) {
                $path = glob( $filePath . $file );
                $registry[$key][$file] = $path[0];
            }

            foreach ($to as $item) {
                if (str_contains($item, ',')) {
                    $parts = array_map('trim', explode(',', $item));
                    $allTo = array_merge($allTo, $parts);
                } elseif (trim($item) !== '') {
                    $allTo[] = trim($item);
                }
            }

            if (!empty($registry)) {
                $msg = [
                    'from' => $this->mailer_from,
                    'cc' => $cc[0],
                    'subject' => 'Реестры за дату ' . $on_date,
                    'body' => ''
                ];
                $email_details = $msg;

                $email = (new Email())
                    ->from($email_details['from'])
                    ->cc($email_details['cc'])
                    ->subject($email_details['subject'])
                    ->html($email_details['body']);

                //Кому
                if (!empty($allTo)) {
                    $email->to($allTo[0]);
                    // Остальные — в копию (addTo)
                    for ($i = 1; $i < count($allTo); $i++) {
                        $email->addTo($allTo[$i]);
                    }
                }

                //Вложение одного/двух файлов
                if (count($registry) == 1) {
                    //один файл
                    $fileNameRegistry = array_keys($registry[0]);
                    $filePath = array_values($registry[0]);

                    $email->attach(fopen($filePath[0], 'r'), $fileNameRegistry[0]);

                } elseif (count($registry) == 3) {
                    //три файла
                    $fileNameRegistry1 = array_keys($registry[0]);
                    $fileNameRegistry2 = array_keys($registry[1]);
                    $fileNameRegistry3 = array_keys($registry[2]);
                    $filePath1 = array_values($registry[0]);
                    $filePath2 = array_values($registry[1]);
                    $filePath3 = array_values($registry[2]);

                    $email
                        ->attach(fopen($filePath1[0], 'r'), $fileNameRegistry1[0])
                        ->attach(fopen($filePath2[0], 'r'), $fileNameRegistry2[0])
                        ->attach(fopen($filePath3[0], 'r'), $fileNameRegistry3[0]);
                } else {
                    //два файла
                    $fileNameRegistry1 = array_keys($registry[0]);
                    $fileNameRegistry2 = array_keys($registry[1]);
                    $filePath1 = array_values($registry[0]);
                    $filePath2 = array_values($registry[1]);

                    $email
                        ->attach(fopen($filePath1[0], 'r'), $fileNameRegistry1[0])
                        ->attach(fopen($filePath2[0], 'r'), $fileNameRegistry2[0]);
                }
                try {
                    $this->mailer->send($email);
                } catch (TransportExceptionInterface $e) {
                    // Раньше ошибка транспорта молча проглатывалась и процесс помечался отправленным.
                    $this->logger->error(sprintf(
                        '[RegistryFileSender] письмо %s не отправлено: %s',
                        $email_details['subject'],
                        $e->getMessage()
                    ));

                    return 'error';
                }
            }
        } else {
            return 'error';
        }

        return 'ok';
    }

}
