<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-3d-host-pos',
    getRequiredEnv('PARAMPOS_3DHOST_MERCHANT_ID'),
    getRequiredEnv('PARAMPOS_3DHOST_USERNAME'),
    getRequiredEnv('PARAMPOS_3DHOST_PASSWORD'),
    getRequiredEnv('PARAMPOS_3DHOST_CLIENT_SECRET'),
    getRequiredEnv('PARAMPOS_3DHOST_TERMINAL_ID'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
