<?php

declare(strict_types=1);

namespace namwansoft\payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use thamtech\uuid\helpers\UuidHelper;

class Beam
{
    private $client;
    private $Url, $UrlHook, $Auth, $Key, $WHookK, $walletId;

    public function __construct(array $key = [], ?string $walletId = null, ?string $UrlHook = null)
    {
        $this->Url = 'https://api.beamcheckout.com/api/';
        $this->Key = $key['key'] ?? null;
        $this->WHookK = $key['hook'] ?? null;
        $this->walletId = $walletId;
        $this->UrlHook = $UrlHook;

        $this->updateClient();
    }
    private function updateClient(): void
    {
        $this->Auth = 'Basic ' . base64_encode("{$this->walletId}:{$this->Key}");
        $this->client = new Client([
            'base_uri' => rtrim($this->Url, '/') . '/',
            'timeout'  => 15,
            'headers'  => [
                'Authorization'   => $this->Auth,
                'Accept'          => 'application/json',
                'Accept-Language' => 'th',
            ],
        ]);
    }
    public function setWalletId(?string $walletId): self
    {
        $this->walletId = $walletId;
        $this->updateClient();
        return $this;
    }
    public function setUrlHook(?string $UrlHook): self
    {
        $this->UrlHook = $UrlHook;
        $this->updateClient();
        return $this;
    }

    /**
     * $refId (Unique identifier for the charge)
     * $amount (Amount to be charged)
     * $timeOut (Time in minutes before the charge expires)
     * $urlReturn (URL to redirect after payment)
     */
    public function createCharge(string $refId = null, float $amount = null, int $timeOut = null, string $urlReturn = null)
    {
        if ($timeOut) {
            $Day = new \DateTime("NOW +" . $timeOut . " minutes", new \DateTimeZone('GMT+0'));
            $assets['expires_at'] = $Day->format(DATE_ATOM);
        }
        try {
            $payload = [
                'merchantID'    => $this->walletId,
                'referenceId'   => $refId ? $refId : 'Ch-' . UuidHelper::uuid(),
                'amount'        => (int)round($amount * 100),
                'returnUrl' => $urlReturn ?? $this->UrlHook,
                'currency' => 'THB',
                'paymentMethod' => [
                    'paymentMethodType' => 'QR_PROMPT_PAY',
                    'qrPromptPay' => [
                        'expiresAt' => $assets['expires_at'] ?? null
                    ]
                ]
            ];
            $response = $this->client->post('v1/charges', ['json' => $payload]);
            return [
                'status' => true,
                'data' => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody,
            ];
        }
    }

    /**
     * $txnId (Unique identifier for the charge)
     */
    public function getCharge(string $txnId = null)
    {
        try {
            $response = $this->client->get('v1/charges' . ($txnId ? '/' . $txnId : ''));
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Unique identifier for the charge)
     */
    public function cancelCharge(string $txnId)
    {
        try {
            $response = $this->client->post("v1/charges/{$txnId}/cancel");
            return [
                'status' => true,
                'data'   => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status'  => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Unique identifier for the transaction)
     * $offset (Offset for pagination)
     * $limit (Number of transactions to retrieve)
     */
    public function transactions(string $txnId = null, int $offset = 0, int $limit = 20)
    {
        $endpoint = ($txnId) ? "v1/transactions/{$txnId}" : 'v1/transactions?' . http_build_query(['offset' => $offset, 'limit'  => $limit]);
        try {
            $response = $this->client->get($endpoint);
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $type (Action type: 'get', 'create', 'delete')
     * $IdOrCode (Pairing code or ID for the bolt connection)
     */
    public function boltConnections(string $type = 'get', string $IdOrCode = null)
    {
        try {
            switch (strtolower($type)) {
                case 'create':
                    $response = $this->client->post('v1/bolt-connections', ['json' => ['pairingCode' => $IdOrCode]]);
                    break;
                case 'get':
                    $response = $this->client->get('v1/bolt-connections' . ($IdOrCode ? '/' . $IdOrCode : ''));
                    break;
                case 'delete':
                    $response = $this->client->delete('v1/bolt-connections' . ($IdOrCode ? '/' . $IdOrCode : ''));
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid request type: ' . $type);
            }
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }
    /**
     * $boltConnectionId (ID of the bolt connection)
     * $refId (Reference ID for the charge)
     * $amount (Amount for the charge)
     * $type (Payment type: PromptPay, Card, TrueMoney, LinePay, ShopeePay, Alipay, WeChatPay)
     */
    public function createBoltIntent(string $boltConnectionId = null, string $refId = null,  float $amount = null, string $type = 'PromptPay')
    {
        $payload = [
            'boltConnectionId' => $boltConnectionId,
            'referenceId' => $refId ? $refId : 'BI-' . UuidHelper::uuid(),
            'amount' => (int)round($amount * 100),
            'expiryDurationInSec' => 180,
            'mode' => ['type' => 'PAIRING'],
            'currency' => 'THB',
        ];
        switch (strtolower($type)) {
            case 'promptpay':
                $payload['paymentMethod']['paymentMethodType'] = 'QR_PROMPT_PAY';
                $payload['paymentMethod']['qrPromptPay'] = new \stdClass();
                break;
            case 'card':
                $payload['paymentMethod']['paymentMethodType'] = 'CARD';
                $payload['paymentMethod']['card'] = new \stdClass();
                break;
            case 'truemoney':
                $payload['paymentMethod']['paymentMethodType'] = 'TRUE_MONEY';
                $payload['paymentMethod']['trueMoney'] = new \stdClass();
                break;
            case 'linepay':
                $payload['paymentMethod']['paymentMethodType'] = 'LINE_PAY';
                $payload['paymentMethod']['linePay'] = new \stdClass();
                break;
            case 'shopeepay':
                $payload['paymentMethod']['paymentMethodType'] = 'SHOPEE_PAY';
                $payload['paymentMethod']['shopeePay'] = new \stdClass();
                break;
            case 'spaylater':
                $payload['paymentMethod']['paymentMethodType'] = 'SPAY_LATER';
                $payload['paymentMethod']['sPayLater'] = new \stdClass();
                break;
            case 'alipay':
                $payload['paymentMethod']['paymentMethodType'] = 'ALIPAY';
                $payload['paymentMethod']['alipay'] = new \stdClass();
                break;
            case 'wechatpay':
                $payload['paymentMethod']['paymentMethodType'] = 'WECHAT_PAY';
                $payload['paymentMethod']['wechatPay'] = new \stdClass();
                break;
        }
        try {
            $response = $this->client->post('v1/bolt-intents', ['json' => $payload]);
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Reference ID for the charge)
     */
    public function cancelBoltIntent(string $txnId)
    {
        try {
            $response = $this->client->patch("v1/bolt-intents/{$txnId}/cancel");
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Reference ID for the charge)
     */
    public function getBoltIntent(string $txnId = null)
    {
        try {
            $response = $this->client->get("v1/bolt-intents" . ($txnId ? '/' . $txnId : ''));
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Unique identifier for the refund)
     */
    public function getRefund(string $txnId)
    {
        try {
            $response = $this->client->get("v1/refunds/{$txnId}");
            return [
                'status' => true,
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'statusCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }
}
