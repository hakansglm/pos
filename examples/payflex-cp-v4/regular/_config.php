<?php

use \Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPayFlexPosAccount(
    'vakifbank-cp',
    (string) getenv('PAYFLEX_CP_MERCHANT_ID'),
    (string) getenv('PAYFLEX_CP_MERCHANT_PASSWORD'),
    (string) getenv('PAYFLEX_CP_TERMINAL_ID'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
