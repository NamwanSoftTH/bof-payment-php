<?php

declare(strict_types=1);

namespace NamwanSoft\Payment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use thamtech\uuid\helpers\UuidHelper;

class Xendit
{
    private $client;
    private $Url, $UrlHook, $Auth, $PriK, $PubK, $WHookK, $walletId;
    private $apiVersion = '2024-11-11';

    public function __construct(array $key = [], $walletId = null, $UrlHook = null)
    {
        $this->Url = 'https://api.xendit.co/';
        $this->PubK = $key['pub'] ?? null;
        $this->PriK = $key['pri'] ?? null;
        $this->WHookK = $key['hook'] ?? null;
        $this->walletId = $walletId;
        $this->UrlHook = $UrlHook;
        $this->Auth = 'Basic ' . base64_encode($this->PriK . ':');

        $this->client = new Client([
            'base_uri' => rtrim($this->Url, '/') . '/',
            'timeout'  => 10,
            'headers'  => [
                'Authorization' => $this->Auth,
                'Accept' => 'application/json',
                'Accept-Language' => 'th',
                'api-version'     => $this->apiVersion,
                'for-user-id' => $this->walletId,
                'webhook-url' => $this->UrlHook,
            ]
        ]);
    }

    /**
     * ตรวจสอบยอดเงินคงเหลือ (Balance)
     */
    public function balance()
    {
        try {
            $response = $this->client->get('balance');
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
     * รายการ (Transaction)
     */
    public function transactions(string $txnId = null)
    {
        try {
            $response = $this->client->get('transactions' . ($txnId ? '/' . $txnId : ''));
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
     * สร้าง Payment Request
     */
    public function createPayment(string $refId, float $amount, string $channelType = 'Qr', string $channelCode = 'PROMPTPAY', int $timeOut = null, array $assets = [])
    {
        if ($timeOut) {
            $Day = new \DateTime("NOW +" . $timeOut . " minutes", new \DateTimeZone('GMT+0'));
            $assets['expires_at'] = $Day->format(DATE_ATOM);
        }
        $channelData = self::$arChannel[$channelType][$channelCode];
        try {
            $payload = [
                'type' => 'PAY',
                'country' => 'TH',
                'currency' => 'THB',
                'channel_code' => $channelData['key'],
                'reference_id' => $refId ? $refId : 'PY-' . UuidHelper::uuid(),
                'request_amount' => $amount,
                'metadata' => []
            ];
            foreach ($channelData['req']['channel_properties'] as $key => $value) {
                $payload['channel_properties'][$value] = ${$value} ?? $assets[$value] ?? null;
            }
            $response = $this->client->post('v3/payment_requests', ['json' => $payload]);
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
     * ดึง Payment Request
     */
    public function getPayment(string $txnId)
    {
        try {
            $response = $this->client->get('v3/payment_requests/' . $txnId);
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
     * ยกเลิก Payment Request
     */
    public function cancelPayment(string $txnId)
    {
        try {
            $response = $this->client->post('v3/payment_requests/' . $txnId . '/cancel');
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
     * สร้างรายการโอนเงินออก (Payout / Disbursement)
     *
     * @param string|null $refId เลขอ้างอิงของออเดอร์/การถอน
     * @param float $amount จำนวนเงินที่ต้องการโอน
     * @param string $channelCode รหัสธนาคาร เช่น TH_KBANK, TH_SCB, TH_PROMPTPAY
     * @param string $accountNumber เลขบัญชีธนาคาร หรือ เบอร์ PromptPay
     * @param string $accountHolderName ชื่อเจ้าของบัญชี
     * @param string $description รายละเอียดการโอน
     * @return array
     */
    public function createPayout(
        string $refId = null,
        float $amount = 0,
        string $channelCode = 'TH_PROMPTPAY',
        string $accountNumber = '',
        string $accountHolderName = '',
        string $description = 'Payout'
    ) {
        return ['status' => false];
        try {
            $payload = [
                'currency'            => 'THB',
                'reference_id'        => $refId ? $refId : 'PO-' . UuidHelper::uuid(),
                'source_of_fund'      => 'BUSINESS_REVENUE',
                'purpose_code'         => 'SALARY',
                'description'         => $description,
                'recipient' => [
                    'type' => 'INDIVIDUAL', // INDIVIDUAL , BUSINESS
                    'given_name' => '',
                    'surname' => '',
                    'relationship' => 'OTHER',
                    'details' => [
                        'personal_mobile_number' => ''
                    ],
                    'account_details' => [
                        'currency' => 'THB',
                        'account_country' => 'TH',
                        'account_number'      => $accountNumber,
                        'account_holder_name' => $accountHolderName,
                        'routing_type_1' => '', //[ "SWIFT", "IBAN", "SORT_CODE", "ABA", "BSB", "WALLET", "CLABE", "MOBILE_NO", "BUSINESS_REG_NO", "NATIONAL_ID" ]
                        'routing_value_1' => ''
                    ],
                    'address' => [
                        "country" => "PH",
                        "street_line_1" => "123 Rizal Avenue",
                        "city" => "Manila",
                        "province_state" => "Metro Manila",
                        "postal_code" => "1000"
                    ]
                ],
                "payout_details" => [
                    "source_currency" => "PHP",
                    "source_amount" => 50000,
                    "destination_currency" => "PHP"
                ],
                'metadata' => []
            ];
            $response = $this->client->post('v3/payouts', [
                'headers' => [
                    'api-version' => '2025-09-01',
                    'idempotency-key' => 'IK-' . ($refId ? $refId : UuidHelper::uuid())
                ],
                'json' => $payload
            ]);
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
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * ดึงข้อมูลสถานะการโอนเงิน (Get Payout Status)
     *
     * @param string $payoutId
     */
    public function getPayout(string $payoutId)
    {
        try {
            $response = $this->client->get('v3/payouts/' . $payoutId, [
                'headers' => [
                    'api-version' => '2025-09-01'
                ]
            ]);
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
                'message' => $e->getMessage(),
                'error'   => $errorResponseBody
            ];
        }
    }

    /**
     * ยกเลิก Payout v3 (ทำได้เฉพาะรายการที่สถานะยังเป็น REQUESTED / PENDING)
     *
     * @param string $payoutId
     */
    public function cancelPayout(string $payoutId)
    {
        try {
            $response = $this->client->post('v3/payouts/' . $payoutId . '/cancel', [
                'headers' => [
                    'api-version' => '2025-09-01'
                ]
            ]);
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
