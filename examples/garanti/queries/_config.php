<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount(
    'garanti',
    getRequiredEnv('GARANTI_MERCHANT_ID'),
    getRequiredEnv('GARANTI_USERNAME'),
    getRequiredEnv('GARANTI_PASSWORD'),
    getRequiredEnv('GARANTI_TERMINAL_ID'),
    null,
    getRequiredEnv('GARANTI_REFUND_USERNAME'),
    getRequiredEnv('GARANTI_REFUND_PASSWORD')
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
