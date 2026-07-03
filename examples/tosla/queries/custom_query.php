<?php

require '../../_common-codes/queries/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'bin' => 415956,
        ],
        'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo',
    ];
}
