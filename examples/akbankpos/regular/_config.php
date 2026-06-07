<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createAkbankPosAccount(
    'akbank-pos',
    (string) getenv('AKBANKPOS_MERCHANT_ID'),
    (string) getenv('AKBANKPOS_TERMINAL_ID'),
    (string) getenv('AKBANKPOS_API_KEY')
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
