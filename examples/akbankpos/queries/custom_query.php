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
            'txnCode'     => '1020',
            'order'       => [
                'orderTrackId' => 'ae15a6c8-467e-45de-b24c-b98821a42667',
            ],
            'payByLink'   => [
                'linkTxnCode'       => '3000',
                'linkTransferType'  => 'SMS',
                'mobilePhoneNumber' => '5321234567',
            ],
            'transaction' => [
                'amount'       => 1.00,
                'currencyCode' => 949,
                'motoInd'      => 0,
                'installCount' => 1,
            ],
        ],
        null,
    ];
}
