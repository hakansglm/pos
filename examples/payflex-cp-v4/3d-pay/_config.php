<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexAccount(
    'vakifbank-cp',
    (string) getenv('PAYFLEX_CP_MERCHANT_ID'),
    (string) getenv('PAYFLEX_CP_MERCHANT_PASSWORD'),
    (string) getenv('PAYFLEX_CP_TERMINAL_ID'),
    PosInterface::MODEL_3D_PAY
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
