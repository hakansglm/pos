<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createIyzicoPosAccount(
    'iyzico',
    (string) getenv('IYZICO_API_KEY'),
    (string) getenv('IYZICO_SECRET_KEY'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
