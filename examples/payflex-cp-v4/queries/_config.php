<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank-cp',
    (string) getenv('PAYFLEX_CP_MERCHANT_ID'),
    (string) getenv('PAYFLEX_CP_MERCHANT_PASSWORD'),
    (string) getenv('PAYFLEX_CP_TERMINAL_ID'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
