<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createAkbankPosAccount(
    'akbank-pos',
    (string) getenv('AKBANKPOS_MERCHANT_ID'),
    (string) getenv('AKBANKPOS_TERMINAL_ID'),
    (string) getenv('AKBANKPOS_API_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
