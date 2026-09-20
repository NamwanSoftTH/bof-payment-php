<?php

namespace NamwanSoft\Payment;

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
    public static function setup($apiEndpoint, $apiKey, $timeout = 10)
    {
        self::$globalEndpoint = $apiEndpoint;
        self::$globalApiKey = $apiKey;
        self::$globalTimeout = $timeout;
    }

    /**
     * Constructor
     * หากไม่ส่งค่ามา จะไปดึงค่าจาก Global ที่ตั้งไว้ตอน setup() มาใช้แทน
     */
    public function __construct($apiEndpoint = null, $apiKey = null, $timeout = null)
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
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json'
            ]
        ]);
    }

    public function createPayment($orderId, $amount)
    {
        try {
            $response = $this->client->post('api/v1/payments', [
                'json' => [
                    'order_id' => $orderId,
                    'amount' => $amount
                ]
            ]);

            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true)
            ];
        } catch (RequestException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
