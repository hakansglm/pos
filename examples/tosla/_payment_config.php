<?php

use Mews\Pos\Model\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';
/** @var string $hostUrl */


$bankTestsUrl = $hostUrl.'/tosla';
$posClass      = \Mews\Pos\Gateway\ToslaPos::class;
$posQueryClass = \Mews\Pos\Factory\PosQueryFactory::getPosQueryClassForGateway($posClass);

$testCards = [
    'visa1'  => [
        'number' => '4546711234567894',
        'year'   => '26',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
        'type'   => CreditCardInterface::CARD_TYPE_VISA,
    ],
    'master' => [
        'number' => '5406675406675403',
        'year'   => '26',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
        'type'   => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
