<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    getRequiredEnv('PARAMPOS_MERCHANT_ID'),
    getRequiredEnv('PARAMPOS_USERNAME'),
    getRequiredEnv('PARAMPOS_PASSWORD'),
    getRequiredEnv('PARAMPOS_CLIENT_SECRET')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
