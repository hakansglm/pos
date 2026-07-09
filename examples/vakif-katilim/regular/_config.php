<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createBoaPosAccount(
    'vakif-katilim',
    getRequiredEnv('VAKIF_KATILIM_MERCHANT_ID'),
    getRequiredEnv('VAKIF_KATILIM_USERNAME'),
    getRequiredEnv('VAKIF_KATILIM_CUSTOMER_NUMBER'),
    getRequiredEnv('VAKIF_KATILIM_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
