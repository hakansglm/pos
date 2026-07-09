<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    getRequiredEnv('ASSECO_CLIENT_ID'),
    getRequiredEnv('ASSECO_USERNAME'),
    getRequiredEnv('ASSECO_PASSWORD'),
    getRequiredEnv('ASSECO_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

// İsteğe bağlı: İşbank İmece Kart — ödeme tamamlama isteğine eklenecek alanlar (bankadan alınır).
// $eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event): void {
//     $data                    = $event->getRequestData();
//     $data['Extra']['IMCKOD'] = '9999';
//     $data['Extra']['FDONEM'] = '5';
//     $event->setRequestData($data);
// });

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
