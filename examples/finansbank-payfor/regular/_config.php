<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPayForPosAccount(
    'qnbfinansbank-payfor',
    getRequiredEnv('FINANSBANK_MERCHANT_ID'),
    getRequiredEnv('FINANSBANK_USERNAME'),
    getRequiredEnv('FINANSBANK_PASSWORD'),
    null,
    \Mews\Pos\Model\Account\PayForPosAccount::MBR_ID_FINANSBANK // ya da PayForAccount::MBR_ID_ZIRAAT_KATILIM
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
