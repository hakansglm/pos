<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay-hosting/';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    (string) getenv('ASSECO_CLIENT_ID'),
    (string) getenv('ASSECO_USERNAME'),
    (string) getenv('ASSECO_PASSWORD'),
    (string) getenv('ASSECO_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Hosting Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY_HOSTING;
