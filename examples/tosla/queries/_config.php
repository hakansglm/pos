<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    (string) getenv('TOSLA_MERCHANT_ID'),
    (string) getenv('TOSLA_USERNAME'),
    (string) getenv('TOSLA_PASSWORD'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
