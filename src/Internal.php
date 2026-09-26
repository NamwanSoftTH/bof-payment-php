<?php

declare(strict_types=1);

namespace namwansoft\payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Internal
{
    // เก็บค่าคอนฟิกแบบ Global (จำค่าไว้ตลอดการทำงานของ Request นั้น)
    private static $globalEndpoint = null;
    private static $globalApiKey = null;
    private static $globalTimeout = 10;

    // ตัวแปรสำหรับ Instance ปัจจุบัน
    private $apiEndpoint;
    private $apiKey;
    private $timeout;

    /**
     * ตั้งค่าเริ่มต้น (เรียกครั้งเดียวตอนเริ่มโปรเจกต์)
     */
    public static function setup(string $apiEndpoint, string $apiKey, int $timeout = 10)
    {
        self::$globalEndpoint = $apiEndpoint;
        self::$globalApiKey = $apiKey;
        self::$globalTimeout = $timeout;
    }

    /**
     * Constructor
     * หากไม่ส่งค่ามา จะไปดึงค่าจาก Global ที่ตั้งไว้ตอน setup() มาใช้แทน
     */
    public function __construct(?string $apiEndpoint = null, ?string $apiKey = null, ?int $timeout = null)
    {
        $this->apiEndpoint = $apiEndpoint ?? self::$globalEndpoint;
        $this->apiKey = $apiKey ?? self::$globalApiKey;
        $this->timeout = $timeout ?? self::$globalTimeout;

        // เช็คว่ามีการตั้งค่าไว้หรือยัง
        if (empty($this->apiEndpoint) || empty($this->apiKey)) {
            throw new \Exception("InternalPayment Error: กรุณาตั้งค่า Endpoint และ API Key โดยเรียกใช้ Internal::setup() ก่อนใช้งาน");
        }

        $this->apiEndpoint = rtrim($this->apiEndpoint, '/');
        $this->client = new Client([
            'base_uri' => $this->apiEndpoint . '/v3/',
            'timeout'  => 15,
            'verify'   => false,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json'
            ]
        ]);
    }

    public function createPayment(string $provider, string $key, string $ref, float $amount, string $urlCallback, array $dataAssets = [])
    {
        $resData = null;
        try {
            $endpoint = $provider . '/create/payment';
            switch ($provider) {
                case 'xendit':
                    $response = $this->client->post($endpoint, ['json' => [
                        'channelType' => $this->arType[$provider][$key][0],
                        'channelCode' => $this->arType[$provider][$key][1],
                        'walletId'   => $dataAssets['walletId'],
                        'webhookUrl' => $urlCallback,
                        'refId'      => $ref,
                        'amount'     => $amount,
                        'metaData'   => $dataAssets['metaData'],
                        'timeOut'    => $dataAssets['timeOut'] ?? 5,
                    ]]);
                    $response = json_decode((string)$response->getBody(), true);
                    if ($response['status']) {
                        $resData['idTxn'] = $response['data']['payment_request_id'];
                        switch ($response['data']['status']) {
                            case 'REQUIRES_ACTION':
                                break;
                            default:
                                break;
                        }
                        if ($key === 'Qr') {
                            $resData['qrString'] = $response['data']['actions'][0]['value'];
                        }
                        if ($response['data']['channel_properties']['expires_at']) {
                            $timeOut = new \DateTime($response['data']['channel_properties']['expires_at']);
                            $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                            $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                        }
                    }
                    break;
                case 'beam':
                    $response = $this->client->post($endpoint, ['json' => [
                        'channelType' => $this->arType[$provider][$key][0],
                        'channelCode' => $this->arType[$provider][$key][0],
                        'walletId'   => $dataAssets['walletId'],
                        'machineId'   => $dataAssets['boltId'],
                        'webhookUrl' => $urlCallback,
                        'refId'      => $ref,
                        'amount'     => $amount,
                        'timeOut'    => $dataAssets['timeOut'] ?? 5,
                    ]]);
                    $response = json_decode((string)$response->getBody(), true);
                    if ($response['status']) {
                        if ($key === 'Qr') {
                            $resData = ['idTxn' => $response['data']['chargeId'], 'qrString'  => $response['data']['encodedImage']['rawData']];
                            $response['data']['expiresAt'] = $response['data']['encodedImage']['expiry'];
                        } else if ($response['data']['status']) {
                            $resData = ['idTxn' => $response['data']['id']];
                        }
                        if ($response['data']['expiresAt']) {
                            $timeOut = new \DateTime($response['data']['expiresAt']);
                            $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                            $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                        }
                    }
                    break;
                default:
                    break;
            }
            return ['status' => $response['status'], 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancelPayment(string $provider, string $key, string $idTxn, array $dataAssets = [])
    {
        $resData = null;
        try {
            $endpoint = $provider . '/cancel/payment';
            $response = $this->client->post($endpoint, ['json' => [
                'channelType' => $this->arType[$provider][$key][0],
                'channelCode' => $this->arType[$provider][$key][1],
                'walletId' => $dataAssets['walletId'],
                'id' => $idTxn,
            ]]);
            $response = json_decode((string)$response->getBody(), true);
            switch ($provider) {
                case 'xendit':
                    $resData['statusTx'] = $response['data']['status'];
                    break;
                case 'beam':
                    $resData['statusTx'] = $response['data']['status'];
                    break;
                default:
                    break;
            }
            return ['status' => $response['status'], 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPayment(string $provider, string $key, string $idTxn, array $dataAssets = [])
    {
        $resData = null;
        try {
            $endpoint = $provider . '/check/payment';
            $response = $this->client->post($endpoint, ['json' => [
                'channelType' => $this->arType[$provider][$key][0],
                'channelCode' => $this->arType[$provider][$key][1],
                'walletId'   => $dataAssets['walletId'],
                'refId' => $dataAssets['refId'],
                'id' => $idTxn,
            ]]);
            $response = json_decode((string)$response->getBody(), true);
            switch ($provider) {
                case 'xendit':
                    if ($response['data']['status'] === 'REQUIRES_ACTION') {
                        $response['data']['status'] = 'ACTIVE';
                    }
                    // if ($response['data']['status'] === 'SUCCEEDED') {
                    // } else if ($response['data']['status'] === 'FAILED') {
                    // } else if ($response['data']['status'] === 'PENDING') {
                    // } else if ($response['data']['status'] === 'EXPIRED') {
                    // } else if ($response['data']['status'] === 'CANCELED') {
                    // } else if ($response['data']['status'] === 'ACTIVE') {
                    // } else if ($response['data']['status'] === 'INACTIVE') {
                    // }
                    $resData['statusTx'] = $response['data']['status'];
                    if ($response['data']['channel_properties']['expires_at']) {
                        $timeOut = new \DateTime($response['data']['channel_properties']['expires_at']);
                        $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                        $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                    }
                    break;
                case 'beam':
                    // if ($response['data']['status'] === 'SUCCEEDED') {
                    // } else if ($response['data']['status'] === 'FAILED') {
                    // } else if ($response['data']['status'] === 'ACTIVE') {
                    // } else if ($response['data']['status'] === 'PAID') {
                    // } else if ($response['data']['status'] === 'EXPIRED') {
                    // } else if ($response['data']['status'] === 'CANCELED') {
                    // } else if ($response['data']['status'] === 'VOIDED') {
                    // } else if ($response['data']['status'] === 'REFUNDED') {
                    // }
                    $resData['statusTx'] = $response['data']['status'];
                    if ($response['data']['expiresAt']) {
                        $timeOut = new \DateTime($response['data']['expiresAt']);
                        $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                        $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                    }
                    break;
                default:
                    break;
            }
            return ['status' => $response['status'], 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getDevice(string $provider, string $key)
    {
        $resData = null;
        try {
            $endpoint = $provider . '/bolt/' . $key;
            switch ($provider) {
                case 'xendit':
                    return ['status' => false];
                    break;
                case 'beam':
                    $response = $this->client->get($endpoint);
                    $response = json_decode((string)$response->getBody(), true);
                    break;
                default:
                    break;
            }
            return ['status' => $response['status'], 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    private $arType = [
        'beam' => [
            'Qr'        => ['Qr'],
            // ''      => ['', ''],
            'PromptPay' => ['PromptPay'],
            'Card'      => ['Card'],
            'TrueMoney' => ['TrueMoney'],
            'LinePay'   => ['LinePay'],
            'ShopeePay' => ['ShopeePay'],
            'WeChatPay' => ['WeChatPay'],
        ],
        'xendit' => [
            'Qr'        => ['Qr', 'PROMPTPAY'],
            // ''      => ['', ''],
            'KBANK'     => ['BANK_ACCOUNT', 'KBANK_MB'],
            'SCB'       => ['BANK_ACCOUNT', 'SCB_MB'],
            'KTB'       => ['BANK_ACCOUNT', 'KTB_MB'],
            'BBL'       => ['BANK_ACCOUNT', 'BBL_MB'],
            'BAY'       => ['BANK_ACCOUNT', 'BAY_MB'],
            // ''      => ['', ''],
            'WeChatPay' => ['EWALLET', 'TH_WECHATPAY'],
            'LinePay'   => ['EWALLET', 'TH_LINEPAY'],
            'TrueMoney' => ['EWALLET', 'TH_TRUEMONEY'],
            'ShopeePay' => ['EWALLET', 'TH_SHOPEEPAY'],
        ]
    ];
}
