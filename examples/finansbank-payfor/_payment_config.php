<?php

use Mews\Pos\Model\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/finansbank-payfor';
$posClass      = \Mews\Pos\Gateway\PayForPos::class;
$posQueryClass = \Mews\Pos\Factory\PosQueryFactory::getPosQueryClassForGateway($posClass);

$testCards = [
    'visa1' => [
        'number' => '4155650100416111',
        'year' => '28',
        'month' => '1',
        'cvv' => '123',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_VISA,
    ],
//    'ziraat-katilim-1' => [
//        OTP: 123456
//        'number' => '5352480048848060',
//        'year' => '31',
//        'month' => '09',
//        'cvv' => '131',
//        'name' => 'John Doe',
//        'type' => CreditCardInterface::CARD_TYPE_VISA,
//    ],
//    'ziraat-katilim-2' => [
//        'number' => '9792096695823360',
//        'year' => '29',
//        'month' => '03',
//        'cvv' => '123',
//        'name' => 'John Doe',
//        'type' => CreditCardInterface::CARD_TYPE_TROY,
//    ],
];
