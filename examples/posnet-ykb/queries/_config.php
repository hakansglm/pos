<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'yapikredi',
    getRequiredEnv('POSNET_YKB_MERCHANT_ID'),
    getRequiredEnv('POSNET_YKB_TERMINAL_ID'),
    getRequiredEnv('POSNET_YKB_POS_ID'),
    '10,10,10,10,10,10,10,10'
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
