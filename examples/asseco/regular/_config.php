<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';


$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    getRequiredEnv('ASSECO_CLIENT_ID'),
    getRequiredEnv('ASSECO_USERNAME'),
    getRequiredEnv('ASSECO_PASSWORD'),
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
