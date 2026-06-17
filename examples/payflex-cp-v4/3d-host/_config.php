<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank-cp',
    (string) getenv('PAYFLEX_CP_MERCHANT_ID'),
    (string) getenv('PAYFLEX_CP_MERCHANT_PASSWORD'),
    (string) getenv('PAYFLEX_CP_TERMINAL_ID'),
    PosInterface::MODEL_3D_HOST
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_HOST;
