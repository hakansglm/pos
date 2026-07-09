<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount(
    'garanti',
    getRequiredEnv('GARANTI_MERCHANT_ID'),
    getRequiredEnv('GARANTI_USERNAME'),
    getRequiredEnv('GARANTI_PASSWORD'),
    getRequiredEnv('GARANTI_TERMINAL_ID'),
    null,
    getRequiredEnv('GARANTI_REFUND_USERNAME'),
    getRequiredEnv('GARANTI_REFUND_PASSWORD')
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
