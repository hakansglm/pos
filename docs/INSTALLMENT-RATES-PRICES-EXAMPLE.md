### Taksit Oranları ve Fiyatları Sorgulama

Bu özellik, hangi taksit seçeneklerinin mevcut olduğunu ve her taksit
seçeneğinde
ödenecek aylık/toplam tutarları sorgulamanıza olanak tanır.

Bu metodlar `PosQueryInterface` üzerinde yer alır; `PosQueryFactory::create()`
ile bir
`PosQueryInterface` nesnesi oluşturarak kullanabilirsiniz.

| Sorgu Türü                                      | Destekleyen Gateway'ler      |
|-------------------------------------------------|------------------------------|
| Taksit Oranları (`TX_TYPE_INSTALLMENT_RATES`)   | ToslaPos, PayTrPos, ParamPos |
| Taksit Fiyatları (`TX_TYPE_INSTALLMENT_PRICES`) | ToslaPos, IyzicoPos          |

#### BIN zorunluluğu

**`getInstallmentRates()`**

| Gateway  | `bin` zorunlu mu?       | Kaç hane? | Diğer zorunlu parametreler |
|----------|-------------------------|-----------|----------------------------|
| ToslaPos | **Evet**                | 6         | —                          |
| PayTrPos | Hayır (gönderilmez)     | —         | —                          |
| ParamPos | Hayır (gönderilmez)     | —         | —                          |

**`getInstallmentPrices()`**

| Gateway   | `bin` zorunlu mu?   | Kaç hane? | Diğer zorunlu parametreler |
|-----------|---------------------|-----------|----------------------------|
| ToslaPos  | Hayır (kullanılmaz) | —         | `amount`                   |
| IyzicoPos | İsteğe bağlı        | 8         | `amount`                   |

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
// Örnek olarak Tosla kullanılıyor:
$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    '424342224432',
    'POS_rwrwwrwr',
    'POS_4343223',
);

$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    // PosQueryInterface nesnesi PosQueryFactory ile oluşturulur
    $posQuery = \Mews\Pos\Factory\PosQueryFactory::create($account, $config, $eventDispatcher);
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {
    var_dump($e);
    exit;
}
```

---

**installment_rates.php (Taksit Oranları)**

```php
<?php

require 'config.php';

// Kartın ilk 6 hanesi (BIN).
// ToslaPos için zorunludur. PayTrPos ve ParamPos için gönderilmez.
$params = ['bin' => 415956];

try {
    /**
     * Dönen yanıt, normalize edilmiş bir array'dir:
     * [
     *   'status'            => 'approved',
     *   'error_message'     => null,
     *   'installment_rates' => [
     *     [
     *       'bank_code'   => 111,      // BKM banka kodu (int); bazı gateway'lerde null (ör. PayTr, ParamPos)
     *       'bank_name'   => 'QNB FinansBank', // banka adı; bazı gateway'lerde null
     *       'card_prefix' => '415956', // kart ön eki; bazı gateway'lerde null
     *       'card_type'   => 'visa',   // CreditCardInterface::CARD_TYPE_*: 'visa'|'master'|'amex'|'troy'|null
     *       'card_class'  => 'credit', // CreditCardInterface::CARD_CLASS_*: 'credit'|'debit'|'prepaid'|null
     *       'card_family' => null,     // CreditCardInterface::CARD_FAMILY_*: 'world'|'axess'|'bonus'|'maximum'|…|null
     *       'rates'       => [
     *         ['installment' => 2, 'rate' => 1.5,  'constant' => 0.0],
     *         ['installment' => 3, 'rate' => 2.0,  'constant' => 0.0],
     *         ['installment' => 6, 'rate' => 2.75, 'constant' => 0.0],
     *         // ...
     *       ],
     *     ],
     *     // PayTr ve ParamPos birden fazla kart ailesi döndürebilir; bank_code/bank_name her zaman null:
     *     [
     *       'bank_code'   => null,
     *       'bank_name'   => null,
     *       'card_prefix' => null,
     *       'card_type'   => null,
     *       'card_class'  => null,
     *       'card_family' => 'axess',
     *       'rates'       => [...],
     *     ],
     *   ],
     *   'all' => [...],  // bankanın ham yanıtı
     * ]
     */
    $response = $posQuery->getInstallmentRates($params);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    // Bu gateway taksit oranı sorgulamasını desteklemiyor
    var_dump($e->getMessage());
    exit;
} catch (\Exception $e) {
    var_dump($e);
    exit;
}

var_dump($response);
```

---

**installment_prices.php (Taksit Fiyatları)**

```php
<?php

require 'config.php';

$params = [
    // Ödeme tutarı (TL) — zorunlu
    'amount' => 1000.0,

    // Kart BIN numarası — ToslaPos için gönderilmez, IyzicoPos için isteğe bağlı.
    // 'bin' => '54308100',
];

try {
    /**
     * Dönen yanıt, normalize edilmiş bir array'dir:
     * [
     *   'status'             => 'approved',
     *   'error_message'      => null,
     *   'installment_prices' => [
     *     [
     *       'bank_code'   => 12,           // BKM banka kodu (int); IyzicoPos doldurur, ToslaPos'ta null
     *       'bank_name'   => 'Halkbank',   // banka adı; IyzicoPos doldurur, ToslaPos'ta null
     *       'card_prefix' => '54308100',   // IyzicoPos doldurur; ToslaPos'ta null
     *       'card_type'   => 'master',     // CreditCardInterface::CARD_TYPE_*; IyzicoPos doldurur, ToslaPos'ta null
     *       'card_class'  => 'credit',     // CreditCardInterface::CARD_CLASS_*; IyzicoPos doldurur, ToslaPos'ta null
     *       'card_family' => 'paraf',      // CreditCardInterface::CARD_FAMILY_*; IyzicoPos doldurur, ToslaPos'ta null
     *       'prices'      => [
     *         ['installment' => 1, 'installment_price' => 1000.0, 'total_price' => 1000.0],
     *         ['installment' => 3, 'installment_price' =>  340.0, 'total_price' => 1020.0],
     *         ['installment' => 6, 'installment_price' =>  175.0, 'total_price' => 1050.0],
     *         // ...
     *       ],
     *     ],
     *     // IyzicoPos sorgusu BIN belirtilmezse birden fazla kart ailesi döndürebilir
     *   ],
     *   'all' => [...],  // bankanın ham yanıtı
     * ]
     */
    $response = $posQuery->getInstallmentPrices($params);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    // Bu gateway taksit fiyatı sorgulamasını desteklemiyor
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
        if ($event->getTxType() === \Mews\Pos\PosQuery\PosQueryInterface::TX_TYPE_INSTALLMENT_RATES) {
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

if ($posQuery::isSupportedQuery(PosQueryInterface::TX_TYPE_INSTALLMENT_RATES)) {
    $rates = $posQuery->getInstallmentRates(['bin' => 415956]);
}

if ($posQuery::isSupportedQuery(PosQueryInterface::TX_TYPE_INSTALLMENT_PRICES)) {
    $prices = $posQuery->getInstallmentPrices(['amount' => 1000.0]);
}
```
