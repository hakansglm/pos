<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'vakif-katilim',
    (string) getenv('VAKIF_KATILIM_MERCHANT_ID'),
    (string) getenv('VAKIF_KATILIM_USERNAME'),
    (string) getenv('VAKIF_KATILIM_CUSTOMER_NUMBER'),
    (string) getenv('VAKIF_KATILIM_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
