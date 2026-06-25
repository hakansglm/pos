<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = AccountFactory::createPayTrPosAccount(
    'paytr',
    (string) getenv('PAYTR_MERCHANT_ID'),
    (string) getenv('PAYTR_MERCHANT_SALT'),
    (string) getenv('PAYTR_MERCHANT_KEY'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
