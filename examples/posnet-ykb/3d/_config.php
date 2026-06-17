<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
// NOT: PosNet testleri lokalde yapilamiyor.
//      Ortam farketmeksizin Yapikrediyle iletisime gecip, sunucu IP adresinize izin verilmesini sağlamanız gerekiyor.


$account = AccountFactory::createPosNetPosAccount(
    'yapikredi',
    (string) getenv('POSNET_YKB_MERCHANT_ID'),
    (string) getenv('POSNET_YKB_TERMINAL_ID'),
    (string) getenv('POSNET_YKB_POS_ID'),
    PosInterface::MODEL_3D_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
