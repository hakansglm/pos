<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'vakif-katilim',
    (string) getenv('VAKIF_KATILIM_MERCHANT_ID'),
    (string) getenv('VAKIF_KATILIM_USERNAME'),
    (string) getenv('VAKIF_KATILIM_CUSTOMER_NUMBER'),
    (string) getenv('VAKIF_KATILIM_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
