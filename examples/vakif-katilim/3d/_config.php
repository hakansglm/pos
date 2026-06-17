<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'vakif-katilim',
    (string) getenv('VAKIF_KATILIM_MERCHANT_ID'),
    (string) getenv('VAKIF_KATILIM_USERNAME'),
    (string) getenv('VAKIF_KATILIM_CUSTOMER_NUMBER'),
    (string) getenv('VAKIF_KATILIM_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
