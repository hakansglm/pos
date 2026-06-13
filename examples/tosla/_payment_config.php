<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/tosla';
$posClass     = \Mews\Pos\Gateways\ToslaPos::class;

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
        'number' => '5571135571135575',
        'year'   => '24',
        'month'  => '12',
        'cvv'    => '000',
        'name'   => 'John Doe',
        'type'   => CreditCardInterface::CARD_TYPE_VISA,
    ],
];
