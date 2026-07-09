<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'albaraka',
    getRequiredEnv('POSNET_V1_MERCHANT_ID'), // 10 haneli üye işyeri numarası
    getRequiredEnv('POSNET_V1_TERMINAL_ID'), // 8 haneli üye işyeri terminal numarası
    getRequiredEnv('POSNET_V1_POS_ID'), // 16 haneli üye işyeri EPOS numarası.
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

// İsteğe bağlı: KOI kampanya kodu — 3D ödeme tamamlama isteğine eklenir (bankadan temin edilir).
// $eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event): void {
//     $data            = $event->getRequestData();
//     $data['KOICode'] = '1'; // 1:Ek Taksit 2:Taksit Atlatma 3:Ekstra Puan 4:Kontur Kazanım 5:Ekstre Erteleme 6:Özel Vade Farkı
//     $event->setRequestData($data);
// });

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
