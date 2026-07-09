<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    getRequiredEnv('INTERPOS_SHOP_CODE'),
    getRequiredEnv('INTERPOS_USER_CODE'),
    getRequiredEnv('INTERPOS_USER_PASS'),
    getRequiredEnv('INTERPOS_MERCHANT_PASS')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
