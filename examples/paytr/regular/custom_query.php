<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(\Mews\Pos\Gateway\AbstractGateway $pos): array
{
    // ornek taksit oranlari istegi
    $account = $pos->getAccount();
    $requestData = [
        'merchant_id'  => $account->getClientId(),
        'request_id'   => date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 10)),
    ];

    $requestData['paytr_token'] = $pos->getCrypt()->hashFromParams($account, $requestData, 'merchant_id:request_id', ':');
    return [
        $requestData,
        'https://www.paytr.com/odeme/taksit-oranlari',
    ];
}
