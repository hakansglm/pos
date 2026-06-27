<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank',
    (string) getenv('PAYFLEX_MPI_MERCHANT_ID'),
    (string) getenv('PAYFLEX_MPI_MERCHANT_PASSWORD'),
    (string) getenv('PAYFLEX_MPI_TERMINAL_ID'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
