<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-host/';

$account = AccountFactory::createPayTrPosAccount(
    'paytr',
    getRequiredEnv('PAYTR_MERCHANT_ID'),
    getRequiredEnv('PAYTR_MERCHANT_SALT'),
    getRequiredEnv('PAYTR_MERCHANT_KEY'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
