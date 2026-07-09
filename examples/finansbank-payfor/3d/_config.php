<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createPayForPosAccount(
    'qnbfinansbank-payfor',
    getRequiredEnv('FINANSBANK_MERCHANT_ID'),
    getRequiredEnv('FINANSBANK_USERNAME'),
    getRequiredEnv('FINANSBANK_PASSWORD'),
    getRequiredEnv('FINANSBANK_STORE_KEY'),
    \Mews\Pos\Model\Account\PayForPosAccount::MBR_ID_FINANSBANK // ya da PayForAccount::MBR_ID_ZIRAAT_KATILIM
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel  = PosInterface::MODEL_3D_SECURE;
