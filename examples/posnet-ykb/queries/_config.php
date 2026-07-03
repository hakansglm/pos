<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'yapikredi',
    (string) getenv('POSNET_YKB_MERCHANT_ID'),
    (string) getenv('POSNET_YKB_TERMINAL_ID'),
    (string) getenv('POSNET_YKB_POS_ID'),
    '10,10,10,10,10,10,10,10'
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
