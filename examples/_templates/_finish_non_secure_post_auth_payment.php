<?php

/** @var \Mews\Pos\PosInterface $pos */
/** @var array<string, mixed> $order */
/** @var string $baseUrl */
/** @var \Mews\Pos\PosInterface::MODEL_* $paymentModel */
/** @var \Mews\Pos\PosInterface::TX_TYPE_* $transaction */

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;

require __DIR__.'/_header.php';

/**
 * alttaki script
 * MODEL_NON_SECURE ve TX_TYPE_PAY_POST_AUTH odemede kredi kart bilgileri olmadan Ön Otorizasyon İşlemi tamamlar.
 */
if (PosInterface::TX_TYPE_PAY_POST_AUTH !== $transaction) {
    header('Location: '.$baseUrl);
    exit();
}

try {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
//         Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//         Ornek:
//         $data = $event->getRequestData();
//         $data['abcd'] = '1234';
//         $event->setRequestData($data);
    });

    dump($order);
    $response = doPayment($pos, $paymentModel, $transaction, $order, null);
} catch (Exception $e) {
    dd($e);
}

if ($pos->isSuccess()) {
    $_SESSION['last_response'] = $response;
}

require __DIR__.'/_render_payment_response.php';
require __DIR__.'/_footer.php';
