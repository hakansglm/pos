<?php

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank',
    getRequiredEnv('PAYFLEX_MPI_MERCHANT_ID'),
    getRequiredEnv('PAYFLEX_MPI_MERCHANT_PASSWORD'),
    getRequiredEnv('PAYFLEX_MPI_TERMINAL_ID'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
