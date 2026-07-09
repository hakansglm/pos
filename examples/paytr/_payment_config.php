<?php

require __DIR__.'/../_main_config.php';
/** @var string $hostUrl */


$bankTestsUrl = $hostUrl.'/paytr';
$posClass      = \Mews\Pos\Gateway\PayTrPos::class;
$posQueryClass = \Mews\Pos\Factory\PosQueryFactory::getPosQueryClassForGateway($posClass);

$testCards = [
    'visa1' => [
        'number' => '4355084355084358',
        'year'   => '30',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
    ],
    'master' => [
        'number' => '5406675406675403',
        'year'   => '26',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
    ],
];

/**
 * @return array<string, mixed>
 */
function createGatewaySpecificOrderFields(): array
{
    return [
        'buyer'           => [
            'email'      => 'test@example.com',
            'name'       => 'John Doe',
            'gsm_number' => '05001234567',
        ],
        'billing_address' => [
            'address' => 'Test Sokak No:1 Istanbul',
        ],
        'basket_items' => [ // optional
            [
                'name'       => 'Binocular',
                'item_count' => 1,
                'price'      => 0.3,
            ],
            [
                'name'       => 'Game code',
                'item_count' => 1,
                'price'      => 9.71,
            ],
        ],
    ];
}
