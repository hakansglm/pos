<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/qr/';

$account = \Mews\Pos\Factory\AccountFactory::createPayForPosAccount(
    'qnbfinansbank-payfor',
    (string) getenv('FINANSBANK_QR_MERCHANT_ID'),
    (string) getenv('FINANSBANK_QR_USERNAME'),
    (string) getenv('FINANSBANK_QR_PASSWORD'),
    (string) getenv('FINANSBANK_QR_STORE_KEY'),
    \Mews\Pos\Model\Account\PayForPosAccount::MBR_ID_FINANSBANK // ya da PayForAccount::MBR_ID_ZIRAAT_KATILIM
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = 'QR Code Payment';
$paymentModel  = PosInterface::MODEL_3D_HOST;
