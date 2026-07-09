<?php
require '_config.php';

require '../../_common-codes/queries/custom_query.php';

/**
 * @return array{array<string, mixed>, non-empty-string|null}
 */
function getCustomRequestData(): array
{
    return [
        [
            'price'     => 100.0,
            'binNumber' => '54308100',
        ],
        'https://sandbox-api.iyzipay.com/payment/iyzipos/installment',
    ];
}
