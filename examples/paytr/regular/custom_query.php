<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(\Mews\Pos\PosInterface $pos):  array
{
    // ornek taksit oranlari istegi
    $account = $pos->getAccount();
    $requestData = [
        'merchant_id'  => $account->getClientId(),
        'request_id'   => date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 10)),
    ];

    $crypt = \Mews\Pos\Factory\CryptFactory::createForGateway(get_class($pos), new \Psr\Log\NullLogger());

    $requestData['paytr_token'] = $crypt->hashFromParams($account, $requestData, 'merchant_id:request_id', ':');
    return [
        $requestData,
        'https://www.paytr.com/odeme/taksit-oranlari',
    ];
}
