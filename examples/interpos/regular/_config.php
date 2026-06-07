<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

//$merchantPass non secure islemler icin kullanilmiyor
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    (string) getenv('INTERPOS_SHOP_CODE'),
    (string) getenv('INTERPOS_USER_CODE'),
    (string) getenv('INTERPOS_USER_PASS'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel  = \Mews\Pos\PosInterface::MODEL_NON_SECURE;
