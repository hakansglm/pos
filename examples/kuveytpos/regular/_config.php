<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'kuveytpos',
    getRequiredEnv('KUVEYTPOS_MERCHANT_ID'),
    getRequiredEnv('KUVEYTPOS_USERNAME'),
    getRequiredEnv('KUVEYTPOS_CUSTOMER_NUMBER'),
    getRequiredEnv('KUVEYTPOS_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
