<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    getRequiredEnv('ASSECO_CLIENT_ID'),
    getRequiredEnv('ASSECO_USERNAME'),
    getRequiredEnv('ASSECO_PASSWORD'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
