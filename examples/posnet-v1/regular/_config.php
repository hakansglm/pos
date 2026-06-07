<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'albaraka',
    (string) getenv('POSNET_V1_MERCHANT_ID'), // 10 haneli üye işyeri numarası
    (string) getenv('POSNET_V1_TERMINAL_ID'), // 8 haneli üye işyeri terminal numarası
    (string) getenv('POSNET_V1_POS_ID'), // 16 haneli üye işyeri EPOS numarası.
    PosInterface::MODEL_NON_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
