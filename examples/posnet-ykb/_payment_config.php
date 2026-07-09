<?php

require __DIR__.'/../_main_config.php';
/** @var string $hostUrl */


$bankTestsUrl = $hostUrl.'/posnet-ykb';
$posClass      = \Mews\Pos\Gateway\PosNetPos::class;
$posQueryClass = \Mews\Pos\Factory\PosQueryFactory::getPosQueryClassForGateway($posClass);

$testCards = [
    'visa1' => [
        'number' => '4048095010857528',
        'year'   => '28',
        'month'  => '05',
        'cvv'    => '454',
        'name'   => 'John Doe',
    ],
];
