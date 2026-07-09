<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank-cp',
    getRequiredEnv('PAYFLEX_CP_MERCHANT_ID'),
    getRequiredEnv('PAYFLEX_CP_MERCHANT_PASSWORD'),
    getRequiredEnv('PAYFLEX_CP_TERMINAL_ID'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
