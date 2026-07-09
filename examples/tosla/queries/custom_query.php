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
            'bin' => 415956,
        ],
        'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo',
    ];
}
