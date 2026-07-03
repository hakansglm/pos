<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createPayTrPosAccount(
    'paytr',
    (string) getenv('PAYTR_MERCHANT_ID'),
    (string) getenv('PAYTR_MERCHANT_SALT'),
    (string) getenv('PAYTR_MERCHANT_KEY'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
