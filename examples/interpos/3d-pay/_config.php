<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    (string) getenv('INTERPOS_SHOP_CODE'),
    (string) getenv('INTERPOS_USER_CODE'),
    (string) getenv('INTERPOS_USER_PASS'),
    (string) getenv('INTERPOS_MERCHANT_PASS')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Pay Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
