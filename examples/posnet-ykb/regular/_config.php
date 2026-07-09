<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetPosAccount(
    'yapikredi',
    getRequiredEnv('POSNET_YKB_MERCHANT_ID'),
    getRequiredEnv('POSNET_YKB_TERMINAL_ID'),
    getRequiredEnv('POSNET_YKB_POS_ID'),
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

// İsteğe bağlı: Joker Vadaa kampanya kodu (bankadan temin edilir).
// $eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event): void {
//     $data                    = $event->getRequestData();
//     $data['sale']['koiCode'] = '1'; // 1:Ek Taksit 2:Taksit Atlatma 3:Ekstra Puan 4:Kontur Kazanım 5:Ekstre Erteleme 6:Özel Vade Farkı
//     $event->setRequestData($data);
// });

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
