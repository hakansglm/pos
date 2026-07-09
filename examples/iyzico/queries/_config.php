<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createIyzicoPosAccount(
    'iyzico',
    getRequiredEnv('IYZICO_API_KEY'),
    getRequiredEnv('IYZICO_SECRET_KEY'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
