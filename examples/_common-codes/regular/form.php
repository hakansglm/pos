<?php

use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

$transaction = $_POST['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$order = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $_POST['currency'] ?? PosInterface::CURRENCY_TRY,
    $_POST['installment'] ?? 0,
    ($_POST['is_recurring'] ?? 0) == 1,
    $_POST['lang'] ?? PosInterface::LANG_TR
);

$card = createCard($pos, $_POST);

require '../../_templates/_finish_non_secure_payment.php';
