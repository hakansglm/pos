<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    (string) getenv('INTERPOS_SHOP_CODE'),
    (string) getenv('INTERPOS_USER_CODE'),
    (string) getenv('INTERPOS_USER_PASS'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
