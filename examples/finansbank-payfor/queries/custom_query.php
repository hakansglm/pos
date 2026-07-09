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
            'SecureType' => 'Inquiry',
            'TxnType'    => 'ParaPuanInquiry',
            'Pan'        => '4155650100416111',
            'Expiry'     => '0125',
            'Cvv2'       => '123',
        ],
        null,
    ];
}
