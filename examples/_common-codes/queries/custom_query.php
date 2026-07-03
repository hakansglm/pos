<?php

use Mews\Pos\PosQuery\PosQueryInterface;

$templateTitle = 'Custom Query';

require '_config.php';
$transaction = PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY;

require '../../_templates/_header.php';

[$requestData, $apiUrl] = getCustomRequestData();

dump($requestData, $apiUrl);

/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(\Mews\Pos\Event\RequestDataPreparedEvent::class, function (\Mews\Pos\Event\RequestDataPreparedEvent $event) {
    dump($event->getRequestData()); // bankaya gonderilecek veri

//    // Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//    // Ornek:
//    if ($event->getTxType() === PosQueryInterface::QUERY_TYPE_CUSTOM_QUERY) {
//        $data         = $event->getRequestData();
//        $data['abcd'] = '1234';
//        $event->setRequestData($data);
//    }
});

try {
    /**
     * $requestData içinde API hesap bilgileri, hash verisi ve bazı sabit değerler
     * eğer zaten bulunmuyorsa kütüphane otomatik ekler.
     *
     * $response: Bankadan dönen cevap array'e dönüştürülür,
     * ancak diğer transaction'larda olduğu gibi mapping/normalization yapılmaz.
     */
    $response = $posQuery->customQuery(
        $requestData,

        // URL optional, bazı gateway'lerde zorunlu.
        // Default olarak configdeki query_api ya da payment_api kullanılır.
        $apiUrl
    );
} catch (Exception $e) {
    dd($e);
}

dump($response);
require '../../_templates/_footer.php';
