
### Ödeme Tarihçe Sorgulama

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

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

$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::create($account, $config['banks'][$account->getBankName()], $eventDispatcher);
} catch (\Mews\Pos\Exception\GatewayClassNotConfiguredException $e) {
    var_dump($e));
    exit;
}
```

**order_history.php**
```php
<?php

require 'config.php';

function createOrderHistoryOrder(string $gatewayClass, array $lastResponse): array
{
    $order = [];
    if (AssecoPos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    } elseif (\Mews\Pos\Gateway\AkbankPos::class === $gatewayClass) {
        if (isset($lastResponse['recurring_id'])) {
            $order = [
                'recurring_id' => $lastResponse['recurring_id'],
            ];
        } else {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
    } elseif (ToslaPos::class === $gatewayClass) {
        $order = [
            'id'               => $lastResponse['order_id'],
            'transaction_date' => $lastResponse['transaction_time'], // odeme tarihi
            'page'             => 1, // optional, default: 1
            'page_size'        => 10, // optional, default: 10
        ];
    } elseif (PayForPos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    } elseif (GarantiPos::class === $gatewayClass) {
        $order = [
            'id'       => $lastResponse['order_id'],
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
    } elseif (\Mews\Pos\Gateway\VakifKatilimPos::class === $gatewayClass) {
        /** @var DateTimeImmutable $txTime */
        $txTime = $lastResponse['transaction_time'];
        $order  = [
            'auth_code'  => $lastResponse['auth_code'],
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    return $order;
}

// ödeme işlemi sonrası dönen veriler:
$_SESSION['last_response'] ?? null

$order = createOrderHistoryOrder(get_class($pos), $lastResponse);

try {
    $response = $pos->orderHistory($order);
} catch (\Error $e) {
    var_dump($e);
    exit;
}
var_dump($response);
```
