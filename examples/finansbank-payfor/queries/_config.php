<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createPayForPosAccount(
    'qnbfinansbank-payfor',
    (string) getenv('FINANSBANK_MERCHANT_ID'),
    (string) getenv('FINANSBANK_USERNAME'),
    (string) getenv('FINANSBANK_PASSWORD'),
    null,
    \Mews\Pos\Model\Account\PayForPosAccount::MBR_ID_FINANSBANK
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
