<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount(
    'garanti',
    (string) getenv('GARANTI_MERCHANT_ID'),
    (string) getenv('GARANTI_USERNAME'),
    (string) getenv('GARANTI_PASSWORD'),
    (string) getenv('GARANTI_TERMINAL_ID'),
    null,
    (string) getenv('GARANTI_REFUND_USERNAME'),
    (string) getenv('GARANTI_REFUND_PASSWORD')
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
