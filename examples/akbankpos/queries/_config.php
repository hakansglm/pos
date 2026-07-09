<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createAkbankPosAccount(
    'akbank-pos',
    getRequiredEnv('AKBANKPOS_MERCHANT_ID'),
    getRequiredEnv('AKBANKPOS_TERMINAL_ID'),
    getRequiredEnv('AKBANKPOS_API_KEY')
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
