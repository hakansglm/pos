<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'kuveytpos',
    (string) getenv('KUVEYTPOS_MERCHANT_ID'),
    (string) getenv('KUVEYTPOS_USERNAME'),
    (string) getenv('KUVEYTPOS_CUSTOMER_NUMBER'),
    (string) getenv('KUVEYTPOS_PASSWORD'),
    PosInterface::MODEL_3D_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
