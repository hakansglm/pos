
### Özel Sorgu

Kütüphanenin desteği olmadığı özel istekleri bu methodla yapabilirsiniz.

> **Not:** `customQuery()` metodu artık `PosQueryInterface` üzerinde yer almaktadır.
> `PosQueryFactory::create()` ile bir `PosQueryInterface` nesnesi oluşturmanız gerekmektedir.

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _payment_config.php dosyalara bakınız
// (örn: /examples/tosla/_payment_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    '424342224432',
    'POS_rwrwwrwr',
    'POS_4343223',
);
$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
$config = require __DIR__.'/pos_test_ayarlar.php';

try {
    // PosQueryInterface nesnesi PosQueryFactory ile oluşturulur
    $posQuery = \Mews\Pos\Factory\PosQueryFactory::create($account, $config['banks'][$account->getBankName()], $eventDispatcher);
} catch (\Mews\Pos\Exception\GatewayClassNotConfiguredException $e) {
    var_dump($e);
    exit;
}
```

**custom_query.php**
```php
<?php

require 'config.php';

/**
 * Eğer requestData içinde API hesap bilgileri, hash verisi ve bazı sabit değerler
 * zaten bulunmuyorsa kütüphane otomatik ekler.
 */
$requestData = [
    'bin' => 415956,
];

/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(\Mews\Pos\Event\PosQueryRequestDataPreparedEvent::class, function (\Mews\Pos\Event\PosQueryRequestDataPreparedEvent $event) {
//    dump($event->getRequestData()); // bankaya gönderilecek veri:
//
//    // Burda istek banka API'na gönderilmeden önce gönderilecek veriyi değiştirebilirsiniz.
//    // Örnek:
//    if ($event->getTxType() === \Mews\Pos\PosQuery\PosQueryInterface::TX_TYPE_CUSTOM_QUERY) {
//        $data         = $event->getRequestData();
//        $data['abcd'] = '1234';
//        $event->setRequestData($data);
//    }
});

try {
    /**
     * $response: Bankadan dönen cevap array'e dönüştürülür,
     * ancak diğer transaction'larda olduğu gibi mapping/normalization yapılmaz.
     */
    $response = $posQuery->customQuery(
        $requestData,

        // URL optional, bazı gateway'lerde zorunlu.
        // Default olarak configdeki query_api ya da payment_api kullanılır.
        'https://prepentegrasyon.tosla.com/api/Payment/GetCommissionAndInstallmentInfo'
    );
} catch (Exception $e) {
    var_dump($e);
    exit;
}
var_dump($response);
```
