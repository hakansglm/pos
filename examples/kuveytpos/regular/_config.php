<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'kuveytpos',
    (string) getenv('KUVEYTPOS_MERCHANT_ID'),
    (string) getenv('KUVEYTPOS_USERNAME'),
    (string) getenv('KUVEYTPOS_CUSTOMER_NUMBER'),
    (string) getenv('KUVEYTPOS_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
