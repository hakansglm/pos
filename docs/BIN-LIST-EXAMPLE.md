### BIN Sorgulama

Bu özellik, bir kart BIN numarasına (kartın ilk 6 veya 8 hanesi) ait kart
bilgilerini
(banka, kart tipi, kart sınıfı, kart ailesi) sorgulamanıza olanak tanır.

Bu metod `PosQueryInterface` üzerinde yer alır; `PosQueryFactory::create()`
ile bir `PosQueryInterface` nesnesi oluşturarak kullanabilirsiniz.

| Sorgu Türü                            | Destekleyen Gateway'ler                   |
|---------------------------------------|-------------------------------------------|
| BIN Sorgulama (`QUERY_TYPE_BIN_LIST`) | GarantiPos, IyzicoPos, ParamPos, PayTrPos |

#### BIN parametresi

| Gateway    | `bin` zorunlu mu? | Kaç hane? | Ek parametreler                                                   | Açıklama                                                    |
|------------|-------------------|-----------|-------------------------------------------------------------------|-------------------------------------------------------------|
| GarantiPos | İsteğe bağlı      | 6         | `ip` (string), `card_class` (`CreditCardInterface::CARD_CLASS_*`) | Verilirse filtrelenmiş sonuçlar, verilmezse tüm BIN tablosu |
| IyzicoPos  | **Evet**          | 8         | —                                                                 | Her zaman tek sonuç döner                                   |
| ParamPos   | İsteğe bağlı      | 6 veya 8  | —                                                                 | Verilirse eşleşen kayıtlar, verilmezse tüm BIN tablosu      |
| PayTrPos   | **Evet**          | 6 veya 8  | —                                                                 | Her zaman tek sonuç döner                                   |

---

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**

```php
<?php
require './vendor/autoload.php';

// AccountFactory'de kullanılacak method Gateway'e göre değişir.
// /examples altındaki _payment_config.php dosyalara bakınız.
// Örnek olarak ParamPos kullanılıyor:
$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param',
    'client_code',
    'client_username',
    'client_password',
);

$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    // PosQueryInterface nesnesi PosQueryFactory ile oluşturulur
    $posQuery = \Mews\Pos\Factory\PosQueryFactory::create($account, $config['banks'][$account->getBankName()], $eventDispatcher);
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {
    var_dump($e);
    exit;
}
```

---

**bin_list.php (BIN Sorgulama)**

```php
<?php

require 'config.php';

$params = [
    // Kartın ilk 6 veya 8 hanesi.
    // IyzicoPos için 8 hane zorunludur.
    // GarantiPos ve ParamPos için isteğe bağlıdır; belirtilmezse tüm BIN tablosu döner.
    'bin' => '41595678',
];

try {
    /**
     * Dönen yanıt, normalize edilmiş bir array'dir:
     * [
     *   'status'        => 'approved',
     *   'error_message' => null,
     *   'bin_list'      => [
     *     [
     *       'bin'         => '415956',   // kart BIN numarası; bazı gateway'lerde null
     *       'bank_code'   => '62',       // BKM banka kodu (string); bilinmiyorsa null
     *       'bank_name'   => 'Garanti Bankası', // banka adı; bilinmiyorsa null
     *       'card_type'   => 'visa',     // CreditCardInterface::CARD_TYPE_*: 'visa'|'master'|'amex'|'troy'|null
     *       'card_class'  => 'credit',   // CreditCardInterface::CARD_CLASS_*: 'credit'|'debit'|'prepaid'|null
     *       'card_family' => 'bonus',    // CreditCardInterface::CARD_FAMILY_*: 'world'|'axess'|'bonus'|…|null
     *     ],
     *     // GarantiPos ve ParamPos BIN belirtilmezse birden fazla kayıt döndürebilir
     *   ],
     *   'all' => [...],  // bankanın ham yanıtı
     * ]
     */
    $response = $posQuery->getBinList($params);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    // Bu gateway BIN sorgusunu desteklemiyor
    var_dump($e->getMessage());
    exit;
} catch (\Exception $e) {
    var_dump($e);
    exit;
}

var_dump($response);
```

---

#### İstek verisini göndermeden önce değiştirme

`PosQueryRequestDataPreparedEvent` olayını dinleyerek bankaya gönderilecek
veriyi
değiştirebilirsiniz:

```php
$eventDispatcher->addListener(
    \Mews\Pos\Event\PosQueryRequestDataPreparedEvent::class,
    function (\Mews\Pos\Event\PosQueryRequestDataPreparedEvent $event): void {
        if ($event->getTxType() === \Mews\Pos\PosQuery\PosQueryInterface::QUERY_TYPE_BIN_LIST) {
            $data         = $event->getRequestData();
            $data['lang'] = 'tr';
            $event->setRequestData($data);
        }
    }
);
```

---

#### Gateway Desteğini Çalışma Zamanında Kontrol Etme

```php
use Mews\Pos\PosQuery\PosQueryInterface;

if ($posQuery::isSupportedQuery(PosQueryInterface::QUERY_TYPE_BIN_LIST)) {
    $result = $posQuery->getBinList(['bin' => '41595678']);
}
```
