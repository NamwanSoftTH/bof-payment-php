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
            'base_uri' => $this->apiEndpoint . '/',
            'timeout'  => $this->timeout,
            'verify'   => false,
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json'
            ]
        ]);
    }

    public function getPayment(string $provider, string $key, string $idTxn, array $dataAssets = [])
    {
        $resData = null;
        try {
            $endpoint = $provider . '/check/payment/' . $this->arType[$provider][$key][0];
            $response = $this->client->post($endpoint, ['json' => [
                'walletId'   => $dataAssets['walletId'],
                'refId' => $dataAssets['refId'],
                'id' => $idTxn,
            ]]);
            $response = json_decode((string)$response->getBody(), true);
            if ($response['status']) {
                $resData = $response;
            }
            switch ($provider) {
                case 'xendit':
                    if ($resData['statusOriginal'] === 'SUCCEEDED') {
                    } else if ($resData['statusOriginal'] === 'FAILED') {
                    } else if ($resData['statusOriginal'] === 'PENDING') {
                    } else if ($resData['statusOriginal'] === 'EXPIRED') {
                    } else if ($resData['statusOriginal'] === 'CANCELED') {
                    } else if ($resData['statusOriginal'] === 'ACTIVE') {
                    } else if ($resData['statusOriginal'] === 'INACTIVE') {
                    }
                    $resData['statusTx'] = $resData['statusOriginal'];
                    if ($resData['expires_at']) {
                        $timeOut = new \DateTime($resData['expires_at']);
                        $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                        $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                    }
                    break;
                case 'beam':
                    if ($resData['statusOriginal'] === 'SUCCEEDED') {
                    } else if ($resData['statusOriginal'] === 'FAILED') {
                    } else if ($resData['statusOriginal'] === 'ACTIVE') {
                    } else if ($resData['statusOriginal'] === 'PAID') {
                    } else if ($resData['statusOriginal'] === 'EXPIRED') {
                    } else if ($resData['statusOriginal'] === 'CANCELED') {
                    } else if ($resData['statusOriginal'] === 'VOIDED') {
                    } else if ($resData['statusOriginal'] === 'REFUNDED') {
                    }
                    $resData['statusTx'] = $resData['statusOriginal'];
                    if ($resData['expiresAt']) {
                        $timeOut = new \DateTime($resData['expiresAt']);
                        $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                        $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                    }
                    break;
                default:
                    break;
            }
            return ['status' => true, 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function createPayment(string $provider, string $key, string $ref, float $amount, string $urlCallback, array $dataAssets = [])
    {
        $resData = null;
        try {
            $endpoint = $provider . '/create/payment/' . $this->arType[$provider][$key][0];
            switch ($provider) {
                case 'xendit':
                    $response = $this->client->post($endpoint, ['json' => [
                        'walletId'   => $dataAssets['walletId'],
                        'webhookUrl' => $urlCallback,
                        'refId'      => $ref,
                        'amount'     => $amount,
                        'metaData'   => $dataAssets['metaData'],
                        'timeOut'    => $dataAssets['timeOut'] ?? 5,
                    ]]);
                    $response = json_decode((string)$response->getBody(), true);
                    if ($response['status'] && $key === 'Qr') {
                        $resData = ['idTxn' => $response['id'], 'qrString'  => $response['qr_string']];
                    } else if ($response['status']) {
                        $resData = ['idTxn' => $response['id']];
                    }
                    if ($response['expires_at']) {
                        $timeOut = new \DateTime($response['expires_at']);
                        $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                        $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                    }
                    break;
                case 'beam':
                    $response = $this->client->post($endpoint, ['json' => [
                        'machineId'   => $dataAssets['boltId'],
                        'refId'      => $ref,
                        'amount'     => $amount,
                        'timeOut'    => $dataAssets['timeOut'] ?? 5,
                    ]]);
                    $response = json_decode((string)$response->getBody(), true);
                    if ($response['status'] && $key === 'Qr') {
                        $resData = ['idTxn' => $response['chargeId'], 'qrString'  => $response['encodedImage']['rawData']];
                        $response['expiresAt'] = $response['encodedImage']['expiry'];
                    } else if ($response['status']) {
                        $resData = ['idTxn' => $response['id']];
                    }
                    if ($response['expiresAt']) {
                        $timeOut = new \DateTime($response['expiresAt']);
                        $timeOut->setTimezone(new \DateTimeZone('Asia/Bangkok'));
                        $resData['timeOut'] = $timeOut->format('Y-m-d H:i:s');
                    }
                    break;
                default:
                    break;
            }
            return ['status' => true, 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancelPayment(string $provider, string $key, string $idTxn, array $dataAssets = [])
    {
        $resData = null;
        try {
            $endpoint = $provider . '/cancel/payment/' . $this->arType[$provider][$key][0];
            switch ($provider) {
                case 'xendit':
                    $response = $this->client->post($endpoint, ['json' => [
                        'walletId'   => $dataAssets['walletId'],
                        'id' => $idTxn,
                    ]]);
                    break;
                case 'beam':
                    $response = $this->client->post($endpoint, ['json' => [
                        'machineId' => $dataAssets['walletId'],
                        'id' => $idTxn,
                    ]]);
                    break;
                default:
                    break;
            }
            $response = json_decode((string)$response->getBody(), true);
            $resData = $response['body'];
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
            return ['status' => true, 'data' => $resData, 'debug' => $response];
        } catch (RequestException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    private $arType = [
        'beam' => [
            'Qr'        => ['QR_CODE'],
            // ''      => ['', ''],
            'PromptPay' => ['PromptPay'],
            'Card'      => ['Card'],
            'TrueMoney' => ['TrueMoney'],
            'LinePay'   => ['LinePay'],
            'ShopeePay' => ['ShopeePay'],
            'WeChatPay' => ['WeChatPay'],
        ],
        'xendit' => [
            'Qr'        => ['QR_CODE', 'PROMPTPAY'],
            // ''      => ['', ''],
            'KBank'     => ['BANK_ACCOUNT', 'KBANK_MB'],
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
