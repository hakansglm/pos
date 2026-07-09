<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createPayTrPosAccount(
    'paytr',
    getRequiredEnv('PAYTR_MERCHANT_ID'),
    getRequiredEnv('PAYTR_MERCHANT_SALT'),
    getRequiredEnv('PAYTR_MERCHANT_KEY'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
