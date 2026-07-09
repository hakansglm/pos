<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    getRequiredEnv('TOSLA_MERCHANT_ID'),
    getRequiredEnv('TOSLA_USERNAME'),
    getRequiredEnv('TOSLA_PASSWORD'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
