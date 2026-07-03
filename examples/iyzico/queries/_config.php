<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createIyzicoPosAccount(
    'iyzico',
    (string) getenv('IYZICO_API_KEY'),
    (string) getenv('IYZICO_SECRET_KEY'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
