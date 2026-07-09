<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-pay-hosting/';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    getRequiredEnv('ASSECO_CLIENT_ID'),
    getRequiredEnv('ASSECO_USERNAME'),
    getRequiredEnv('ASSECO_PASSWORD'),
    getRequiredEnv('ASSECO_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Hosting Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY_HOSTING;
