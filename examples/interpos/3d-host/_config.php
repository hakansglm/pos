<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

//$userCode ve $userPass 3d-host odemede kullanilmiyor.
$userCode = '';
$userPass = '';

$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    (string) getenv('INTERPOS_SHOP_CODE'),
    $userCode,
    $userPass,
    (string) getenv('INTERPOS_MERCHANT_PASS')
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
