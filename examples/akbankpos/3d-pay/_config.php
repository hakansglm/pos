<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createAkbankPosAccount(
    'akbank-pos',
    getRequiredEnv('AKBANKPOS_MERCHANT_ID'),
    getRequiredEnv('AKBANKPOS_TERMINAL_ID'),
    getRequiredEnv('AKBANKPOS_API_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
