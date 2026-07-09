<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = AccountFactory::createGarantiPosAccount(
    'garanti',
    getRequiredEnv('GARANTI_MERCHANT_ID'),
    getRequiredEnv('GARANTI_USERNAME'),
    getRequiredEnv('GARANTI_PASSWORD'),
    getRequiredEnv('GARANTI_TERMINAL_ID'),
    getRequiredEnv('GARANTI_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
