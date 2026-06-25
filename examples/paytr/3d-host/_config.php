<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = AccountFactory::createPayTrPosAccount(
    'paytr',
    (string) getenv('PAYTR_MERCHANT_ID'),
    (string) getenv('PAYTR_MERCHANT_SALT'),
    (string) getenv('PAYTR_MERCHANT_KEY'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
