
### Tarihçe Sorgulama

> **Not:** `history()` v2'de artık `PosQueryInterface` üzerinde yer almaktadır.
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
// (örn: /examples/akbankpos/_payment_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'akbank', // pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    // PosQueryInterface nesnesi PosQueryFactory ile oluşturulur
    $posQuery = \Mews\Pos\Factory\PosQueryFactory::create($account, $config, $eventDispatcher);
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {
    var_dump($e);
    exit;
}
```

**history.php**
```php
<?php

require 'config.php';

function createHistoryOrder(string $gatewayClass, string $ip): array
{
    $order  = [];
    $txTime = new \DateTimeImmutable();
    if (\Mews\Pos\Gateway\PayForPos::class === $gatewayClass) {
        $order = [
            // ödeme tarihi
            'transaction_date' => $txTime,
        ];
    } elseif (\Mews\Pos\Gateway\VakifKatilimPos::class === $gatewayClass) {
        $order = [
            'page'       => 1,
            'page_size'  => 20,
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
        $order = [
            'ip'         => $ip,
            'page'       => 1, // optional
            // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
            'start_date' => $txTime,
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateway\AkbankPos::class === $gatewayClass) {
        $order = [
            // Gün aralığı 1 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,
        ];
//        ya da batch number ile (batch number ödeme işleminden alınan response'da bulunur):
//        $order = [
//            'batch_num' => 24,
//        ];
    } elseif (\Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass) {
        $order = [
            'page'       => 1,
            'page_size'  => 20,
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime,
        ];
    } elseif (\Mews\Pos\Gateway\PayTrPos::class === $gatewayClass) {
        $order = [
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime,
        ];
    } elseif (\Mews\Pos\Gateway\ParamPos::class === $gatewayClass) {
        $order = [
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime,
        ];
    }

    return $order;
}

// $posQuery->getAccount() hesap sınıfını döner; gateway sınıfını almak için config'den bakınız
$gatewayClass = get_class($posQuery); // ToslaPosQuery, AkbankPosQuery gibi değil!
// Doğru kullanım: gateway sınıfını account bankName'inden config'e bakarak belirleyiniz.
// Örneklerde bu işlem _payment_config.php içinde $posClass değişkeniyle yönetilmektedir.

$order = createHistoryOrder(\Mews\Pos\Gateway\AkbankPos::class, '127.0.0.1');

try {
    $response = $posQuery->history($order);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    // Bu gateway tarihçe sorgulamasını desteklemiyor
    var_dump($e->getMessage());
    exit;
} catch (\Error $e) {
    var_dump($e);
    exit;
}
var_dump($response);
```
