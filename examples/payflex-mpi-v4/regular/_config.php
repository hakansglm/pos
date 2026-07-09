<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank',
    getRequiredEnv('PAYFLEX_MPI_MERCHANT_ID'),
    getRequiredEnv('PAYFLEX_MPI_MERCHANT_PASSWORD'),
    getRequiredEnv('PAYFLEX_MPI_TERMINAL_ID'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
