<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-3d-host-pos',
    (int) getenv('PARAMPOS_3DHOST_MERCHANT_ID'),
    (string) getenv('PARAMPOS_3DHOST_USERNAME'),
    (string) getenv('PARAMPOS_3DHOST_PASSWORD'),
    (string) getenv('PARAMPOS_3DHOST_CLIENT_SECRET')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
