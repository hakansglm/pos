<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    getRequiredEnv('PARAMPOS_MERCHANT_ID'),
    getRequiredEnv('PARAMPOS_USERNAME'),
    getRequiredEnv('PARAMPOS_PASSWORD'),
    getRequiredEnv('PARAMPOS_CLIENT_SECRET')
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
