<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPayForPosAccount(
    'qnbfinansbank-payfor',
    (string) getenv('FINANSBANK_MERCHANT_ID'),
    (string) getenv('FINANSBANK_USERNAME'),
    (string) getenv('FINANSBANK_PASSWORD'),
    null,
    \Mews\Pos\Model\Account\PayForPosAccount::MBR_ID_FINANSBANK // ya da PayForAccount::MBR_ID_ZIRAAT_KATILIM
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
