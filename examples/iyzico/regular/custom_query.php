<?php

require '../../_common-codes/regular/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'price'     => 100.0,
            'binNumber'   => '54308100',
        ],
        'https://sandbox-api.iyzipay.com/payment/iyzipos/installment',
    ];
}
