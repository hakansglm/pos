<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    (int) getenv('PARAMPOS_MERCHANT_ID'),
    (string) getenv('PARAMPOS_USERNAME'),
    (string) getenv('PARAMPOS_PASSWORD'),
    (string) getenv('PARAMPOS_CLIENT_SECRET')
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
