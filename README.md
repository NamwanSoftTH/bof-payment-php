# payment-php

composer update mycompany/internal-payment

require 'vendor/autoload.php';

use namwansoft\payment\Internal;

$payment = new Internal();

<?php
// ไฟล์ index.php หรือไฟล์ตั้งค่าเริ่มต้นของระบบ
require 'vendor/autoload.php';

use MyCompany\Payment\InternalPayment;

// 1. โหลดค่าจาก .env หรือ Database
$paymentUrl = 'http://payment-service.internal';
$paymentToken = 'secret-token-for-shop';

// 2. สั่ง Setup ครั้งเดียวจบ!
InternalPayment::setup($paymentUrl, $paymentToken);
