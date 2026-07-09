<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    getRequiredEnv('PARAMPOS_MERCHANT_ID'),
    getRequiredEnv('PARAMPOS_USERNAME'),
    getRequiredEnv('PARAMPOS_PASSWORD'),
    getRequiredEnv('PARAMPOS_CLIENT_SECRET')
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
