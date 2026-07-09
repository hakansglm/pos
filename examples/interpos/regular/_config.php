<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

//$merchantPass non secure islemler icin kullanilmiyor
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    getRequiredEnv('INTERPOS_SHOP_CODE'),
    getRequiredEnv('INTERPOS_USER_CODE'),
    getRequiredEnv('INTERPOS_USER_PASS'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel  = \Mews\Pos\PosInterface::MODEL_NON_SECURE;
