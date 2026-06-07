<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/iyzico';
$posClass = \Mews\Pos\Gateways\IyzicoPos::class;

$testCards = [
    'visa1' => [
        'number' => '4603450000000000',
        //'number' => '4111111111111129', //insufficient funds
        //'number' => '4141111111111115', //Success but mdStatus is 4
        'year'   => '26',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
    ],
    'yabanci' => [
        'number' => '5400010000000004',
        'year'   => '26',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
    ],
];

function createGatewaySpecificOrderFields(string $ip, string $paymentModel): array
{
    $data = [
        'buyer' => [
            'id'                   => 'BY789',
            'name'                 => 'John',
            'surname'              => 'Doe',
            'identity_number'      => '74300864791',
            'email'                => 'email@email.com',
            'gsm_number'           => '+905350000000',
            'registration_address' => 'Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1',
            'city'                 => 'Istanbul',
            'country'              => 'Turkey',
            'zip_code'             => '34732',
            'ip'                   => $ip,
        ],
        'shipping_address' => [
            'contact_name' => 'John Doe',
            'city'         => 'Istanbul',
            'country'      => 'Turkey',
            'address'      => 'Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1',
            'zip_code'     => '34732',
        ],
        'billing_address' => [
            'contact_name' => 'John Doe',
            'city'         => 'Istanbul',
            'country'      => 'Turkey',
            'address'      => 'Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1',
            'zip_code'     => '34732',
        ],
        'basket_items' => [
            [
                'id'        => 'BI101',
                'name'      => 'Binocular',
                'category1' => 'Collectibles',
                'category2' => 'Accessories',
                'item_type' => 'PHYSICAL',
                'price'     => 0.3,
            ],
            [
                'id'        => 'BI102',
                'name'      => 'Game code',
                'category1' => 'Game',
                'category2' => 'Online Game Items',
                'item_type' => 'VIRTUAL',
                'price'     => 9.71,
            ],
        ],
    ];

    if (\Mews\Pos\PosInterface::MODEL_3D_HOST === $paymentModel) {
        $data['enabled_installments'] = [1,3,4,6];
        //$data['enabled_installments'] = [1,2,3,4,6,9,12];
    } else {
        $data['payment_channel'] = 'WEB'; //WEB|MOBILE|MOBILE_WEB|MOBILE_IOS|MOBILE_ANDROID|MOBILE_WINDOWS|MOBILE_TABLET|MOBILE_PHONE
    }

    return $data;
}
