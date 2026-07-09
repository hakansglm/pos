<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'vakif-katilim',
    getRequiredEnv('VAKIF_KATILIM_MERCHANT_ID'),
    getRequiredEnv('VAKIF_KATILIM_USERNAME'),
    getRequiredEnv('VAKIF_KATILIM_CUSTOMER_NUMBER'),
    getRequiredEnv('VAKIF_KATILIM_PASSWORD'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
