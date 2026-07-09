<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    getRequiredEnv('PARAMPOS_MERCHANT_ID'), // CLIENT_CODE
    getRequiredEnv('PARAMPOS_USERNAME'), // CLIENT_USERNAME Kullanıcı adı
    getRequiredEnv('PARAMPOS_PASSWORD'), // CLIENT_PASSWORD Şifre
    getRequiredEnv('PARAMPOS_CLIENT_SECRET') // GUID Üye İşyeri ait anahtarı
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
