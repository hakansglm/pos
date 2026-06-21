<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'payten_v3_hash',
    (string) getenv('ASSECO_CLIENT_ID'),
    (string) getenv('ASSECO_USERNAME'),
    (string) getenv('ASSECO_PASSWORD'),
    PosInterface::MODEL_3D_HOST,
    (string) getenv('ASSECO_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
