<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'albaraka',
    getRequiredEnv('POSNET_V1_MERCHANT_ID'),
    getRequiredEnv('POSNET_V1_TERMINAL_ID'),
    getRequiredEnv('POSNET_V1_POS_ID'),
    '10,10,10,10,10,10,10,10'
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
