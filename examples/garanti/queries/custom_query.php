<?php

require '../../_common-codes/queries/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'Version'     => 'v0.00',
            'Customer'    => [
                'IPAddress'    => '1.1.111.111',
                'EmailAddress' => 'Cem@cem.com',
            ],
            'Order'       => [
                'OrderID'     => 'SISTD5A61F1682E745B28871872383ABBEB1',
                'GroupID'     => '',
                'Description' => '',
            ],
            'Transaction' => [
                'Type'                  => 'bininq',
                'InstallmentCnt'        => '',
                'Amount'                => 1,
                'CurrencyCode'          => '949',
                'CardholderPresentCode' => '0',
                'MotoInd'               => 'N',
            ],
        ],
        null,
    ];
}
