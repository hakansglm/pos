<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createAkbankPosAccount(
    'akbank-pos',
    (string) getenv('AKBANKPOS_MERCHANT_ID'),
    (string) getenv('AKBANKPOS_TERMINAL_ID'),
    (string) getenv('AKBANKPOS_API_KEY')
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
