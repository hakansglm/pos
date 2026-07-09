<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'kuveytpos',
    getRequiredEnv('KUVEYTPOS_MERCHANT_ID'),
    getRequiredEnv('KUVEYTPOS_USERNAME'),
    getRequiredEnv('KUVEYTPOS_CUSTOMER_NUMBER'),
    getRequiredEnv('KUVEYTPOS_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
