<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank',
    (string) getenv('PAYFLEX_MPI_MERCHANT_ID'),
    (string) getenv('PAYFLEX_MPI_MERCHANT_PASSWORD'),
    (string) getenv('PAYFLEX_MPI_TERMINAL_ID'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
