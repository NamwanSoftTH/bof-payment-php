<?php

namespace NamwanSoft\Payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Internal
{
    private $client;

    public function __construct($apiEndpoint, $apiKey)
    {
        $this->client = new Client([
            'base_uri' => rtrim($apiEndpoint, '/') . '/',
            'timeout'  => 10.0,
            'headers'  => [
                'Authorization' => 'Bearer ' . $apiKey,
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
