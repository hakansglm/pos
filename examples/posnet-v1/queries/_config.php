<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'albaraka',
    (string) getenv('POSNET_V1_MERCHANT_ID'),
    (string) getenv('POSNET_V1_TERMINAL_ID'),
    (string) getenv('POSNET_V1_POS_ID'),
    '10,10,10,10,10,10,10,10'
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
