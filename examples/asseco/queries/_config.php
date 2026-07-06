<?php

require '../_payment_config.php';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    (string) getenv('ASSECO_CLIENT_ID'),
    (string) getenv('ASSECO_USERNAME'),
    (string) getenv('ASSECO_PASSWORD'),
);

$posQuery      = getPosQuery($account, $eventDispatcher);
$posQueryClass = get_class($posQuery);
