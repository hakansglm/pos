<?php

require '../../_common-codes/queries/custom_query.php';

function getCustomRequestData(): array
{
    return [
        [
            'MerchantOrderId' => '2126497214',
            'InstallmentCount' => '0',
            'Amount'           => '120',
            'DisplayAmount'    => '120',
            'FECAmount'        => '0',
            'FECCurrencyCode'  => '0949',
            'Addresses'        => [
                'VPosAddressContract' => [
                    'Type'        => '1',
                    'Name'        => 'Mahmut Sami YAZAR',
                    'PhoneNumber' => '324234234234',
                    'OrderId'     => '0',
                    'AddressLine1' => 'Deneme Mah.',
                    'AddressLine2' => '',
                    'City'         => 'Ankara',
                    'District'     => '',
                    'PostalCode'   => '',
                    'Country'      => 'TR',
                    'Company'      => '',
                ],
            ],
        ],
        null,
    ];
}
