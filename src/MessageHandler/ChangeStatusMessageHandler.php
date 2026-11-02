<?php


namespace App\MessageHandler;


use App\Message\ChangeStatusMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class ChangeStatusMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        #[Autowire('%env(PROCESSING_API_URL)%')]
        private string $processingUrl
    ) {}

    public function __invoke(ChangeStatusMessage $message)
    {

        $token = $message->getToken();
        $id = $message->getId();
        $status = $message->getStatus();
        $description = $message->getDescription();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => rtrim($this->processingUrl, '/').'/api/v1/transactions/set_status/'.$id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "{
    'status': $status,
    'error_description': $description
}",
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '. $token,
                'Content-Type: application/json'
            ),
        ));
        $str = curl_exec($curl);
        curl_exec($curl);
        curl_close($curl);

        //TODO Доделать информативные логи
        $result = null;

        if (json_decode($str, true)){
            $res = json_decode($str, true);
            if (isset($res['status'])) {
                $result = $res['status'];
            }
            if (isset($res['errors'])) {
                $result = $res['errors'][0]['detail'];
            }
        }

        if ($result) {
            if ($result === $status) {
                return Command::SUCCESS;
            } else {
                return Command::INVALID;
            }

        } else {
            return Command::FAILURE;
        }
    }
}
