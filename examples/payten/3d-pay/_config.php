<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'payten_v3_hash',
    (string) getenv('PAYTEN_TERMINAL_ID'),
    (string) getenv('PAYTEN_USERNAME'),
    (string) getenv('PAYTEN_PASSWORD'),
    PosInterface::MODEL_3D_PAY,
    (string) getenv('PAYTEN_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
