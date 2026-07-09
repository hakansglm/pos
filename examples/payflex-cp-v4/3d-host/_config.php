<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank-cp',
    getRequiredEnv('PAYFLEX_CP_MERCHANT_ID'),
    getRequiredEnv('PAYFLEX_CP_MERCHANT_PASSWORD'),
    getRequiredEnv('PAYFLEX_CP_TERMINAL_ID'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_HOST;
