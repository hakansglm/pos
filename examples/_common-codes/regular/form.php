<?php

/** @var \Mews\Pos\PosInterface $pos */
/** @var string $baseUrl */
/** @var string $ip */
/** @var PosInterface::MODEL_* $paymentModel */

use Mews\Pos\PosInterface;

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
