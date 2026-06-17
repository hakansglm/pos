<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createPayForAccount(
    'qnbfinansbank-payfor',
    (string) getenv('FINANSBANK_MERCHANT_ID'),
    (string) getenv('FINANSBANK_USERNAME'),
    (string) getenv('FINANSBANK_PASSWORD'),
    PosInterface::MODEL_3D_SECURE,
    (string) getenv('FINANSBANK_STORE_KEY'),
    \Mews\Pos\Entity\Account\PayForPosAccount::MBR_ID_FINANSBANK // ya da PayForAccount::MBR_ID_ZIRAAT_KATILIM
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel  = PosInterface::MODEL_3D_SECURE;
