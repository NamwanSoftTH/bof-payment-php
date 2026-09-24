<?php

namespace NamwanSoft\Payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use thamtech\uuid\helpers\UuidHelper;

class Beam
{
    private $client;
    private $Url, $UrlHook, $Auth, $Key, $WHookK, $walletId;
    private $apiVersion = '2024-11-11';

    public function __construct(array $key = [], $walletId = null, $UrlHook = null)
    {
        $this->Url = 'https://api.beamcheckout.com/api/';
        $this->Key = $key['key'];
        $this->WHookK = $key['hook'];
        $this->walletId = $walletId;
        $this->UrlHook = $UrlHook;
        $this->Auth = 'Basic ' . base64_encode("{$this->walletId}:{$this->Key}");

        $this->client = new Client([
            'base_uri' => rtrim($this->Url, '/') . '/',
            'timeout'  => 10,
            'headers'  => [
                'Authorization' => $this->Auth,
                'Accept' => 'application/json',
                'Accept-Language' => 'th',
            ]
        ]);
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
                'amount'        => $amount * 100,
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
                'data'    => json_decode($response->getBody()->getContents(), true)
            ];
        } catch (RequestException $e) {
            $errorResponseBody = $e->hasResponse()
                ? json_decode($e->getResponse()->getBody()->getContents(), true)
                : null;
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Unique identifier for the charge)
     */
    public function getCharge($txnId = null)
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
    public function transactions($txnId = null, $offset = 0, $limit = 20)
    {
        $endpoint = ($txnId) ? 'v1/transactions/' . $txnId : 'v1/transactions?' . http_build_query(['offset' => $offset, 'limit'  => $limit]);
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
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $type (Action type: 'get', 'create', 'delete')
     * $IdOrCode (Pairing code or ID for the bolt connection)
     */
    public function boltConnections($type = 'get', $IdOrCode = null)
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
        $playload = [
            'boltConnectionId' => $boltConnectionId,
            'referenceId' => $refId ? $refId : 'BI-' . UuidHelper::uuid(),
            'amount' => $amount * 100,
            'expiryDurationInSec' => 180,
            'mode' => ['type' => 'PAIRING'],
            'currency' => 'THB',
        ];
        switch (strtolower($type)) {
            case 'promptpay':
                $playload['paymentMethod']['paymentMethodType'] = 'QR_PROMPT_PAY';
                $playload['paymentMethod']['qrPromptPay'] = new stdClass();
                break;
            case 'card':
                $playload['paymentMethod']['paymentMethodType'] = 'CARD';
                $playload['paymentMethod']['card'] = new stdClass();
                break;
            case 'truemoney':
                $playload['paymentMethod']['paymentMethodType'] = 'TRUE_MONEY';
                $playload['paymentMethod']['trueMoney'] = new stdClass();
                break;
            case 'linepay':
                $playload['paymentMethod']['paymentMethodType'] = 'LINE_PAY';
                $playload['paymentMethod']['linePay'] = new stdClass();
                break;
            case 'shopeepay':
                $playload['paymentMethod']['paymentMethodType'] = 'SHOPEE_PAY';
                $playload['paymentMethod']['shopeePay'] = new stdClass();
                break;
            case 'spaylater':
                $playload['paymentMethod']['paymentMethodType'] = 'SPAY_LATER';
                $playload['paymentMethod']['sPayLater'] = new stdClass();
                break;
            case 'alipay':
                $playload['paymentMethod']['paymentMethodType'] = 'ALIPAY';
                $playload['paymentMethod']['alipay'] = new stdClass();
                break;
            case 'wechatpay':
                $playload['paymentMethod']['paymentMethodType'] = 'WECHAT_PAY';
                $playload['paymentMethod']['wechatPay'] = new stdClass();
                break;
        }
        try {
            $response = $this->client->post('v1/bolt-intents', ['json' => $playload]);
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
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Reference ID for the charge)
     */
    public function cancelBoltIntent($txnId = null)
    {
        try {
            $response = $this->client->patch('v1/bolt-intents' . ($txnId ? '/' . $txnId : '') . '/cancel');
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
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * $txnId (Reference ID for the charge)
     */
    public function getBoltIntent($txnId = null)
    {
        try {
            $response = $this->client->get('v1/bolt-intents' . ($txnId ? '/' . $txnId : ''));
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
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }


    public static $arChannel = [
        'Qr' => [
            'PROMPTPAY' => [
                'key' => 'PROMPTPAY',
                'req' => [
                    'channel_properties' => ['expires_at']
                ]
            ]
        ],
        'MobileBanking' => [
            'KBANK' => [
                'key' => 'KBANK_MOBILE_BANKING',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url', 'account_mobile_number']
                ]
            ],
            'SCB' => [
                'key' => 'SCB_MOBILE_BANKING',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url', 'account_mobile_number']
                ]
            ],
            'KTB' => [
                'key' => 'KTB_MOBILE_BANKING',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url', 'account_mobile_number']
                ]
            ],
            'BAY' => [
                'key' => 'KRUNGSRI_MOBILE_BANKING',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url', 'account_mobile_number']
                ]
            ],
            'BBL' => [
                'key' => 'BBL_MOBILE_BANKING',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url', 'account_mobile_number']
                ]
            ]
        ],
        'EWallet' => [
            'SHOPEEPAY' => [
                'key' => 'SHOPEEPAY',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url']
                ]
            ],
            'WECHATPAY' => [
                'key' => 'WECHATPAY',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url', 'pending_return_url']
                ]
            ],
            'LINEPAY' => [
                'key' => 'LINEPAY',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url']
                ]
            ],
            'TRUEMONEY' => [
                'key' => 'TRUEMONEY',
                'req' => [
                    'channel_properties' => ['success_return_url', 'failure_return_url']
                ]
            ]
        ]
    ];

    public static $arPayoutChannel = [
        200 => 'TH_PROMPTPAY',
        1  => 'TH_KKB',
        2  => 'TH_BBL',
        3  => 'TH_BAY',
        4  => 'TH_SCB',
        5  => 'TH_KTB',
        7  => 'TH_UOB',
        9  => 'TH_TISCO',
        10 => 'TH_ICBC',
        11 => 'TH_KNB',
        13 => 'TH_SC',
        14 => 'TH_GHB',
        15 => 'TH_LHB',
        16 => 'TH_GSB',
        17 => 'TH_BAA',
        18 => 'TH_IBT',
        19 => 'TH_CIMB',
        20 => 'TH_TTB',
        25 => 'TH_CITI',
        26 => 'TH_SMBC',
        27 => 'TH_TTCR',
        28 => 'TH_MIZUHO',
        29 => 'TH_DEUTSCHE',
        30 => 'TH_HKS',
        31 => 'TH_BNP',
        32  => 'TH_CLICX',
    ];
}
