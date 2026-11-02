<?php


namespace App\Service;


use Doctrine\DBAL\SQL\Parser\Exception;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiSession
{
    /**
     * @var string[]
     */
    private array $headers;

    public function __construct(
        protected HttpClientInterface $httpClient,
        protected Security            $security,
        protected string              $login,
        protected string              $password,
        protected string              $encryption_key,
        protected string              $api_url
    )
    {
        $this->headers = [
            'Content-Type' => 'application/json'
        ];
    }


    public function login(): ?array
    {
        $response = $this->authCall('POST', 'session', [
            "type" => "admin",
            "password" => $this->password,
            "login" => $this->login,
        ]);

        if (isset($response['code'])) {
            return $response;
        }
//        dd($response['token']);
        $this->setToken($this->security->getUser()->getUserIdentifier(), $response['token']);

        return $response;
    }

    public function get($route, $data = false): array
    {
        return $this->call('GET', $route, $data);
    }


    /**
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function authCall($method, $route, $data = false): array
    {
        $options = [
            'headers' => $this->headers,
        ];
        $options['json'] = $data;

        $response = $this->httpClient->request($method, $this->api_url . '/api/v1/' . $route, $options);

        if ($response->getStatusCode() === 200) {
            return $response->toArray();
        }

        if ($response->getStatusCode() === 401) {
            return ['code' => $response->getStatusCode(), 'error' => $response->getContent(false)];
        }

        return ['code' => $response->getStatusCode(), 'error' => $response->getContent(false)];
    }
    private function call($method, $route, $data = false): array
    {
        $options = [
            'auth_bearer' => $this->getToken($this->security->getUser()->getUserIdentifier()),
            'headers' => $this->headers,
        ];

        if ($method === 'GET' && $data) {
            $options['query'] = $data;
        } else if ($data) {
            $options['json'] = $data;
        }

        $response = $this->httpClient->request($method, $this->api_url . '/api/v1/' . $route, $options);

        if ($response->getStatusCode() === 200) {
            return $response->toArray();
        }

        if ($response->getStatusCode() === 401) {
//            dd($response->getStatusCode());
//            $this->login();
//            return ['error' => $response->getStatusCode(), 'message' => $response->getInfo('debug')];
            return ['code' => $response->getStatusCode(), 'error' => $response->getContent(false)];
        }

        return ['code' => $response->getStatusCode(), 'error' => $response->getContent(false)];
    }

    public function getSession(string $session, string $token): string
    {

        $options = [
            'auth_bearer' => $token,
            'headers' => $this->headers,
        ];

        $response = $this->httpClient->request('GET', $this->api_url . '/api/v1/session', $options);

        if ($response->getStatusCode() === 200) {

            $arr = $response->toArray();
            return $arr['token'];

        }

        return $this->createSession('POST', $session);
    }

    public function createSession($method, string $session): string
    {

        $credits = [];

        if ($session == 'admin.session') {
            $credits = [
                'type' => 'admin',
                'login' => $this->login,
                'password' => $this->password
            ];
        }

        $options = ['headers' => $this->headers];
        $options['json'] = $credits;

        $response = $this->httpClient->request($method, $this->api_url . '/api/v1/session', $options);

        if ($response->getStatusCode() === 200) {
            $arr = $response->toArray();

            if (isset($arr['token'])) {

                $this->setToken($this->security->getUser()->getUserIdentifier(), $response['token']);

                return $arr['token'];
            } else return 'no session';
        }

        return 'no session';
    }

    private function setToken($user, $token): void
    {
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($token, $cipher, $this->encryption_key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $this->encryption_key, true);
        $ciphertext = base64_encode($iv . $hmac . $ciphertext_raw);

        $cache = new FilesystemAdapter();
        $session = $cache->getItem(hash('sha256', $user))->set($ciphertext);
        $cache->save($session);
    }

    private function getToken($user): ?string
    {
        $cache = new FilesystemAdapter();
        $session = $cache->getItem(hash('sha256', $user))->get();

        $c = base64_decode($session);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $plaintext = openssl_decrypt($ciphertext_raw, $cipher, $this->encryption_key, OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $this->encryption_key, true);

        if (hash_equals($hmac, $calcmac)) {
            return $plaintext;
        }

        return null;
    }

    public function updateStatusSession($method): string
    {
        try {
            $credits = [
                'type' => 'admin',
                'login' => $this->login,
                'password' => $this->password
            ];

            $options = ['headers' => $this->headers];
            $options['json'] = $credits;

            $response = $this->httpClient->request($method, $this->api_url . '/api/v1/session', $options);

            if ($response->getStatusCode() === 200) {

                $arr = $response->toArray();
                return $arr['token'] ?? 'no session';
            }

            return 'no session';

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
