<?php

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/akbankpos';
$posClass = \Mews\Pos\Gateway\AkbankPos::class;

$testCards = [
    'visa1' => [
        // OTP 123456
        'number' => '4355093000315232',
        'year'   => '28',
        'month'  => '01',
        'cvv'    => '264',
        'name'   => 'John Doe',
    ],
];
