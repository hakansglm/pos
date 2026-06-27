<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'albaraka',
    (string) getenv('POSNET_V1_MERCHANT_ID'), // 10 haneli üye işyeri numarası
    (string) getenv('POSNET_V1_TERMINAL_ID'), // 8 haneli üye işyeri terminal numarası
    (string) getenv('POSNET_V1_POS_ID'), // 16 haneli üye işyeri EPOS numarası.
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
