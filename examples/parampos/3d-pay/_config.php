<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    (string) getenv('PARAMPOS_MERCHANT_ID'),
    (string) getenv('PARAMPOS_USERNAME'),
    (string) getenv('PARAMPOS_PASSWORD'),
    (string) getenv('PARAMPOS_CLIENT_SECRET')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
