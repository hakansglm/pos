<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    (string) getenv('TOSLA_MERCHANT_ID'),
    (string) getenv('TOSLA_USERNAME'),
    (string) getenv('TOSLA_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
