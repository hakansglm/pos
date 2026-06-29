<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'albaraka',
    (string) getenv('POSNET_V1_MERCHANT_ID'), // 10 haneli üye işyeri numarası
    (string) getenv('POSNET_V1_TERMINAL_ID'), // 8 haneli üye işyeri terminal numarası
    (string) getenv('POSNET_V1_POS_ID'), // 16 haneli üye işyeri EPOS numarası.
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

// İsteğe bağlı: KOI kampanya kodu (bankadan temin edilir).
// $eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event): void {
//     $data            = $event->getRequestData();
//     $data['KOICode'] = '1'; // 1:Ek Taksit 2:Taksit Atlatma 3:Ekstra Puan 4:Kontur Kazanım 5:Ekstre Erteleme 6:Özel Vade Farkı
//     $event->setRequestData($data);
// });

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
