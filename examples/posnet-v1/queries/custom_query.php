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
            'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => 'N',
            'PaymentFacilitatorData' => null,
            'AdditionalInfoData'     => null,
            'CardInformationData'    => [
                'CardHolderName' => 'deneme deneme',
                'CardNo'         => '5400619360964581',
                'Cvc2'           => '056',
                'ExpireDate'     => '2001',
            ],
            'ThreeDSecureData'       => null,
        ],
        null,
    ];
}
