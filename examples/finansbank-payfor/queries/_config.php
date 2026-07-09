<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createPayForPosAccount(
    'qnbfinansbank-payfor',
    getRequiredEnv('FINANSBANK_MERCHANT_ID'),
    getRequiredEnv('FINANSBANK_USERNAME'),
    getRequiredEnv('FINANSBANK_PASSWORD'),
    null,
    \Mews\Pos\Model\Account\PayForPosAccount::MBR_ID_FINANSBANK
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
