<?php

require __DIR__.'/../_main_config.php';
/** @var string $hostUrl */


$bankTestsUrl = $hostUrl.'/parampos-3d-host';
$posClass     = \Mews\Pos\Gateway\Param3DHostPos::class;
