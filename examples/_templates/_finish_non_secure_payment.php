<?php

use Mews\Pos\Event\RequestDataPreparedEvent;

// dinamik olarak ilgili bunkanin regular klasor altindaki _config.php yuklenir
// ornegin: asseco/regular/_config.php
require_once '_config.php';
require '../../_templates/_header.php';

/**
 * alttaki script
 * MODEL_NON_SECURE ve TX_TYPE_PAY_AUTH, TX_TYPE_PAY_PRE_AUTH odemede kullanicidan
 * kredi karti alindiktan sonra odemeyi tamamlar.
 */
// non secure odemede POST ile kredi kart bilgileri gelmesi bekleniyor.
if ((($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')) {
    header('Location: '.$baseUrl);
    exit();
}
// İsteğe bağlı: istek bankaya gönderilmeden önce düzenlemek için bu listener'ı kullanın.
// Banka özelinde örnekler için ilgili bankanın _config.php dosyasına bakınız.
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event): void {
    // $data = $event->getRequestData();
    // $data['ozel_alan'] = 'deger';
    // $event->setRequestData($data);
});
try {
    $response = doPayment($pos, $paymentModel, $transaction, $order, $card);
} catch (Exception $e) {
    dd($e);
}

if ($pos->isSuccess()) {
    $_SESSION['last_response'] = $response;
}

require __DIR__.'/_render_payment_response.php';
require __DIR__.'/_footer.php';
