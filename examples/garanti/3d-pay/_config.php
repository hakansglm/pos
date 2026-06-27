<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = AccountFactory::createGarantiPosAccount(
    'garanti',
    (string) getenv('GARANTI_MERCHANT_ID'),
    (string) getenv('GARANTI_USERNAME'),
    (string) getenv('GARANTI_PASSWORD'),
    (string) getenv('GARANTI_TERMINAL_ID'),
    (string) getenv('GARANTI_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
