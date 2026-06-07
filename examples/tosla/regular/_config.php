<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    (string) getenv('TOSLA_MERCHANT_ID'),
    (string) getenv('TOSLA_USERNAME'),
    (string) getenv('TOSLA_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
