<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    (string) getenv('PARAMPOS_MERCHANT_ID'),
    (string) getenv('PARAMPOS_USERNAME'),
    (string) getenv('PARAMPOS_PASSWORD'),
    (string) getenv('PARAMPOS_CLIENT_SECRET')
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
