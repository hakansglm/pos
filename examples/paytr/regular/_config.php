<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = AccountFactory::createPayTrPosAccount(
    'paytr',
    getRequiredEnv('PAYTR_MERCHANT_ID'),
    getRequiredEnv('PAYTR_MERCHANT_SALT'),
    getRequiredEnv('PAYTR_MERCHANT_KEY'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
