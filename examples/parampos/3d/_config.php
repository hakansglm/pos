<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    (int) getenv('PARAMPOS_MERCHANT_ID'), // CLIENT_CODE Terminal ID
    (string) getenv('PARAMPOS_USERNAME'), // CLIENT_USERNAME Kullanıcı adı
    (string) getenv('PARAMPOS_PASSWORD'), // CLIENT_PASSWORD Şifre
    (string) getenv('PARAMPOS_CLIENT_SECRET') // GUID Üye İşyeri ait anahtarı
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
