<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';
/** @var string $bankTestsUrl */
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */


$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'asseco',
    getRequiredEnv('ASSECO_CLIENT_ID'),
    getRequiredEnv('ASSECO_USERNAME'),
    getRequiredEnv('ASSECO_PASSWORD'),
    getRequiredEnv('ASSECO_STORE_KEY')
);

$pos = getGateway($account, $eventDispatcher);

// İsteğe bağlı: İşbank İmece Kart ödemesi — bankadan alınan IMCKOD ve dönem sayısı (FDONEM) gereklidir.
// $eventDispatcher->addListener(\Mews\Pos\Event\Before3DFormHashCalculatedEvent::class, function (\Mews\Pos\Event\Before3DFormHashCalculatedEvent $event): void {
//     $inputs           = $event->getFormInputs();
//     $inputs['IMCKOD'] = '9999';
//     $inputs['FDONEM'] = '5';
//     $event->setFormInputs($inputs);
// });

// İsteğe bağlı: callbackUrl eklenmesi (ödeme sonucu bildirimi için).
// $eventDispatcher->addListener(\Mews\Pos\Event\Before3DFormHashCalculatedEvent::class, function (\Mews\Pos\Event\Before3DFormHashCalculatedEvent $event): void {
//     $inputs                = $event->getFormInputs();
//     $inputs['callbackUrl'] = $inputs['failUrl'];
//     $inputs['refreshTime'] = '10'; // saniye cinsinden; callbackUrl'nin doğru çalışması için gerekli
//     $event->setFormInputs($inputs);
// });

$transaction = $_SESSION['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
