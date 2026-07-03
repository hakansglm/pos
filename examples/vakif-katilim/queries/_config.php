<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'vakif-katilim',
    (string) getenv('VAKIF_KATILIM_MERCHANT_ID'),
    (string) getenv('VAKIF_KATILIM_USERNAME'),
    (string) getenv('VAKIF_KATILIM_CUSTOMER_NUMBER'),
    (string) getenv('VAKIF_KATILIM_PASSWORD'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
