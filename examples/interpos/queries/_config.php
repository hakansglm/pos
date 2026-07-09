<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    getRequiredEnv('INTERPOS_SHOP_CODE'),
    getRequiredEnv('INTERPOS_USER_CODE'),
    getRequiredEnv('INTERPOS_USER_PASS'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
