<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'yapikredi',
    (string) getenv('POSNET_YKB_MERCHANT_ID'),
    (string) getenv('POSNET_YKB_TERMINAL_ID'),
    (string) getenv('POSNET_YKB_POS_ID'),
    PosInterface::MODEL_NON_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
