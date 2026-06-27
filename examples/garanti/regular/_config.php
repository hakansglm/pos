<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount(
    'garanti',
    (string) getenv('GARANTI_MERCHANT_ID'),
    (string) getenv('GARANTI_USERNAME'),
    (string) getenv('GARANTI_PASSWORD'),
    (string) getenv('GARANTI_TERMINAL_ID'),
    null,
    (string) getenv('GARANTI_REFUND_USERNAME'),
    (string) getenv('GARANTI_REFUND_PASSWORD')
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
