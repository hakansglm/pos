
### Örnek 3D Secure ve 3D Pay ödemenin Modal Box'ta iframe kullanarak örneği

3D Secure ve 3D Pay ödemede kullanmanız gereken kodlar arasında tek fark `$paymentModel` değeridir.
```php
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
// veya
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_PAY;
```
Kütüphane içersinde ödeme modele göre farklı kodlar çalışacak.

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

**config.php (Ayar dosyası)**
```php
<?php
require './vendor/autoload.php';

// Configure session with security options
session_set_cookie_params([
    'samesite' => 'None',
    'secure'   => true,
    'httponly' => true, // Javascriptin session'a erişimini engelliyoruz.
]);
session_start();

$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

// AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
// /examples altındaki _config.php dosyalara bakınız
// (örn: /examples/akbankpos/3d/_config.php)
$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'akbank', //pos config'deki ayarın index name'i
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    'yourStoreKey'
);

$eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

try {
    $config = require __DIR__.'/pos_test_ayarlar.php';

    $pos = \Mews\Pos\Factory\PosFactory::create($account, $config['banks'][$account->getBankName()], $eventDispatcher);
} catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {
    var_dump($e));
    exit;
}
```

**form.php (kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod)**

```php
<?php

require 'config.php';

// Sipariş bilgileri
$order = [
    'id'          => 'BENZERSIZ-SIPARIS-ID',
    'amount'      => 1.01,
    'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY, //optional. default: TRY
    'installment' => 0, //0 ya da 1'den büyük değer, optional. default: 0

    // Success ve Fail URL'ler farklı olabilir ama kütüphane success ve fail için aynı kod çalıştırır.
    // success_url ve fail_url'lerin aynı olmasın fayda var çünkü bazı gateyway'ler tek bir URL kabul eder.
    'success_url' => 'https://example.com/response.php',
    'fail_url'    => 'https://example.com/response.php',

    // lang degeri verilmezse config'de tanimlanan dil veya default olarak LANG_TR kullanılacak.
    'lang' => \Mews\Pos\Gateway\PosInterface::LANG_TR, // Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların dili.
];

/**
 * NOT! kod örneği basit tutma amaçlı order'i (ve diğer verileri) session'a kaydediyoruz.
 * Siz veri tabanı ya da farklı bir storage mediumda kullanabilirsiniz.
 */
$_SESSION['order'] = $order;
$_SESSION['tx'] = $transactionType;

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

if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım olacak.
    $_SESSION['card'] = $_POST;
}

try {
    $formData = $pos->get3DFormData(
        $order,
        $paymentModel,
        $transactionType,
        $card,
        /**
         * MODEL_3D_SECURE veya MODEL_3D_PAY ödemelerde kredi kart verileri olmadan
         * form verisini oluşturmak için true yapabilirsiniz.
         * Yine de bazı gatewaylerde kartsız form verisi oluşturulamıyor.
         */
        false, // optional, default: false
        /**
         * İsteğe bağlı: 3D form verisinin dönüş formatını belirtir.
         * PosInterface::FORM_FORMAT_ARRAY: gateway URL, HTTP metodu ve form alanlarını içeren dizi döner.
         * PosInterface::FORM_FORMAT_HTML: hazır HTML form string'i döner.
         * Belirtilmezse (null) gateway'in varsayılan formatı kullanılır.
         * Desteklenmeyen format talep edilirse UnsupportedFormFormatException fırlatılır.
         */
        // null // $formFormat, default: null
    );
} catch (\InvalidArgumentException $e) {
    // örneğin kart bilgisi sağlanmadığında bu exception'i alırsınız.
    var_dump($e);
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    var_dump($e);
} catch (\Throwable $e) {
    var_dump($e);
    exit;
}

$gatewayUrl   = null;
$renderedForm = null;
if (is_string($formData)) {
    $renderedForm = $formData;
} elseif ($formData['method'] === 'GET' && $formData['inputs'] === []) {
    $gatewayUrl = $formData['gateway'];
} else {
    ob_start();
    include('_redirect_iframe_or_popup_window_form.php');
    $renderedForm = ob_get_clean();
}
?>


<!--
    $renderedForm içinde 3D formun verileriyle oluşturulan HTML form bulunur.
    alttaki kodlar ise bu $renderedForm verisini seçilen $flowType'a göre
    iframe modal box içine veya pop up window içine basar.
    NOT: ornek JS kodlar Bootstrap 5 kullanarak yapilmistir.
-->
<div class="alert alert-dismissible" role="alert" id="result-alert">
    <!-- buraya odeme basarili olup olmadini alttaki JS kodlariyla basiyoruz. -->
</div>
<pre id="result-response">
    <!-- buraya odeme sonuc verilerinin alttaki JS kodlariyla basiyoruz-->
</pre>

<script>
    document.getElementById('result-alert').style.display = 'none';
    let messageReceived = false;

    /**
     * Bankadan geri websitenize yönlendirme yapıldıktan sonra alınan sonuca göre başarılı/başarısız alert box'u gösterir.
     */
    let displayResponse = function (event) {
        let alertBox = document.getElementById('result-alert');
        let data = JSON.parse(atob(event.data));

        let resultResponse = document.getElementById('result-response');
        resultResponse.appendChild(document.createTextNode(JSON.stringify(data, null, '\t')));

        if (data.status === 'approved') {
            alertBox.appendChild(document.createTextNode('payment successful'));
            alertBox.classList.add('alert-info');
        } else {
            alertBox.classList.add('alert-danger');
            alertBox.appendChild(document.createTextNode('payment failed: ' + (data.error_message ?? data.md_error_message)));
        }

        alertBox.style.display = 'block';
    }
</script>

    <div class="modal fade" tabindex="-1" role="dialog" id="iframe-modal" data-keyboard="false" data-backdrop="static">
        <div class="modal-dialog" role="document" style="width: 426px;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="iframe-modal-body">
                </div>
            </div>
        </div>
    </div>
    <script>
        /**
         * Bankadan geri websitenize yönlendirme yapıldıktan sonra ödeme sonuç verisi iframe/popup içinde olur.
         * Modal box'ta açılan iframe'den ana pencereye JS'in windowlar arası Message API'ile ödeme sonucunu ana window'a gönderiyoruz.
         * Alttaki kod ise bu message API event'ni dinler,
         * message (yani bankadan dönen ödeme sonucu) aldığında sonucu kullanıcıya ana window'da gösterir
         */
        window.addEventListener('message', function (event) {
            messageReceived = true;
            displayResponse(event);
            let myModal = bootstrap.Modal.getInstance(document.getElementById('iframe-modal'));
            myModal.hide();
        });

        const iframeSrc    = <?= json_encode($gatewayUrl); ?>;
        const iframeSrcdoc = <?= json_encode($renderedForm); ?>;

        /**
         * modal box'ta iframe ile ödeme yöntemi seçilmiş.
         * modal box içinde yeni iframe oluşturuyoruz ve iframe içine $renderedForm verisini basıyoruz.
         */
        let iframe = document.createElement('iframe');
        document.getElementById("iframe-modal-body").appendChild(iframe);
        iframe.style.height = '500px';
        iframe.style.width = '410px';
        if (iframeSrc !== null) {
            iframe.src = iframeSrc;
        } else {
            iframe.srcdoc = iframeSrcdoc;
        }
        let modalElement = document.getElementById('iframe-modal');
        let myModal = new bootstrap.Modal(modalElement, {
            keyboard: false
        })
        myModal.show();

        modalElement.addEventListener('hidden.bs.modal', function () {
            if (!messageReceived) {
                let alertBox = document.getElementById('result-alert');
                alertBox.classList.add('alert-danger');
                alertBox.appendChild(document.createTextNode('modal box kapatildi'));
                alertBox.style.display = 'block';
            }
        });
    </script>
```

**response.php (gateway'den döndükten sonra çalışacak kod)**

```php
<?php

require 'config.php';

$order = $_SESSION['order'];
$card  = null;
if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
    // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım.
    $cardData = $_SESSION['card'];
    unset($_SESSION['card']);
    $card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
        $pos,
        $cardData['card_number'],
        $cardData['card_year'],
        $cardData['card_month'],
        $cardData['card_cvv'],
        $cardData['card_name'],
        $cardData['card_type']
  );
}

// Ödeme tamamlanıyor
$gatewayResponseData = $_POST;
if (get_class($pos) === \Mews\Pos\Gateway\PayFlexCPV4Pos::class) {
    $gatewayResponseData = $_GET;
}

try  {
    $response = $pos->payment(
        $paymentModel,
        $order,
        $transactionType,
        $card,
        $gatewayResponseData
    );

    // Ödeme başarılı mı?
    $pos->isSuccess();
} catch (Mews\Pos\Exception\HashMismatchException $e) {
    /**
     * Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
     * Veya Banka API bilgileriniz hatalı ise de oluşur.
     * Eğer kütühaneden dolayı hash doğrulama hatası alıyorsanız, issue oluşturunuz.
     * Issue çözülene kadar geçici olarak disable_3d_hash_check: true ayarla hash doğrulamasını devre dışı bırakabilirsiniz.
     * Güvenlik açısından disable_3d_hash_check: false olarak kullanılması tavsiye edilmez.
     */
}
?>


<script>
    if (window.parent) {
        // response.php iframe'de calisti
        // odeme sonucunu ana window'a yani form.php'e gonderiyoruz.
        window.parent.postMessage(`<?= base64_encode(json_encode($response)); ?>`);
    }
</script>
```
