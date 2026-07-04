
### Non Secure (Regular) ödeme kodu

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

$paymentModel = \Mews\Pos\PosInterface::MODEL_NON_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

// API kullanıcı bilgileri
// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _config.php dosyalara bakınız
// (örn: /examples/akbankpos/3d/_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::create($account, $config, $eventDispatcher);
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {
    var_dump($e));
    exit;
}
```

**index.php (kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod)**

```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'          => 'BENZERSIZ-SIPARIS-ID',
    'amount'      => 1.01,
    'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY, //optional. default: TRY
    'installment' => 0, //0 ya da 1'den büyük değer, optional. default: 0

    // lang degeri verilmezse config'de tanimlanan dil veya default olarak LANG_TR kullanılacak.
    'lang' => \Mews\Pos\Gateway\PosInterface::LANG_TR, // Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların  dili.
];

// Kredi kartı bilgileri
try {
$card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
        $pos,
        $_POST['card_number'],
        $_POST['card_year'],
        $_POST['card_month'],
        $_POST['card_cvv'],
        $_POST['card_name'],

        // kart tipi Gateway'e göre zorunlu, alabileceği örnek değer: "visa"
        // alabileceği alternatif değerler için \Mews\Pos\Model\Card\CreditCardInterface'a bakınız.
        $_POST['card_type'] ?? null
  );
} catch (\Mews\Pos\Exception\CardTypeRequiredException $e) {
    // bu gateway için kart tipi zorunlu
} catch (\Mews\Pos\Exception\CardTypeNotSupportedException $e) {
    // sağlanan kart tipi bu gateway tarafından desteklenmiyor
}

// Ödeme tamamlanıyor
try {
    $response = $pos->payment(
        $paymentModel,
        $order,
        $transactionType,
        $card
    );
} catch (\Error $e) {
    var_dump($e);
    exit;
}

var_dump($response);
// response içeriği için /examples/template/_payment_response.php dosyaya bakınız.

// Ödeme başarılı mı?
if ($pos->isSuccess()) {
    // NOT: Ödeme durum sorgulama, iptal ve iade işlemleri yapacaksanız $response değerini saklayınız.
}
```
