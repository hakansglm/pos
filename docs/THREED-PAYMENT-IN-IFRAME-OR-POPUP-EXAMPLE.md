
## 3D Secure ve 3D Pay Ödemeleri: iFrame ve Popup Window Örnekleri

Kullanıcıyı banka sayfasına yönlendirmeden — yani kullanıcının web sitenizi terk etmesini sağlamadan —
ödeme yaptırmak istiyorsanız iki yöntemden birini kullanabilirsiniz:

| Yöntem | Açıklama |
|---|---|
| **Modal Box (iFrame)** | Banka sayfası, kendi sayfanızda açılan bir modal içindeki iFrame'de çalışır. Önerilen yöntemdir. |
| **Popup Window** | Banka sayfası ayrı bir tarayıcı penceresinde açılır. Tarayıcılar popup'ları engelleyebilir. |

### Dikkat edilmesi gerekenler

> iFrame ve popup window ödemeleri tarayıcı güvenlik kısıtlaması (SameSite cookie, CORS) nedeniyle
> **localhost'ta denenemez**. Public erişimli (HTTPS) bir sunucuda test edilmelidir.

> Popup window yöntemi bazı tarayıcılar tarafından engellenebilir.
> Bu nedenle **modal box içinde iFrame** kullanımı tavsiye edilir.

---

### Ödeme Modeli Farkı

3D Secure ve 3D Pay ödemelerinde kullanmanız gereken kodlar arasındaki tek fark `$paymentModel` değeridir:

```php
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
// veya
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_PAY;
```

Kütüphane, ödeme modeline göre otomatik olarak doğru akışı çalıştırır.

---

### Dosya Yapısı

```
config.php     — Gateway bağlantısı ve ortak ayarlar
form.php       — Kredi kart bilgilerini alır, 3D formunu hazırlar ve iframe/popup'ı başlatır
response.php   — Banka yönlendirmesinden sonra ödemeyi tamamlar, sonucu ana pencereye iletir
```

---

### config.php

```sh
$ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
```

```php
<?php

require './vendor/autoload.php';

// iFrame veya popup window içindeki response.php'in session'a erişebilmesi için
// SameSite=None;Secure gereklidir.
session_set_cookie_params([
    'samesite' => 'None',
    'secure'   => true,
    'httponly' => true,
]);
session_start();

$paymentModel    = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
$transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

// AccountFactory'de kullanılacak metot gateway'e göre değişir.
// Detaylar için /examples altındaki _config.php dosyalarına bakınız.
// Örnek: /examples/akbankpos/3d/_config.php
$account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
    'akbank',          // pos config'deki banka index adı
    'yourClientID',
    'yourKullaniciAdi',
    'yourSifre',
    'yourStoreKey'
);

$eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

$config = require __DIR__ . '/pos_test_ayarlar.php';

try {
    $pos = \Mews\Pos\Factory\PosFactory::create(
        $account,
        $config['banks'][$account->getBankName()],
        $eventDispatcher
    );
} catch (\Mews\Pos\Exception\GatewayClassNotConfiguredException $e) {
    var_dump($e);
    exit;
}
```

---

### form.php

Kullanıcıdan kredi kartı bilgileri alındıktan sonra çalışır.
Kart bilgilerinin nasıl alınacağına dair form örneği için `/examples/_templates/_credit_card_form.php` dosyasına bakınız.
3DHost ödemelerinde kart bilgisi gerekmez.

```php
<?php

require 'config.php';

/**
 * Sipariş bilgileri.
 *
 * NOT: IyzicoPos, KuveytPos ve PayTrPos ekstra alanlar gerektirir.
 * Detaylar için /examples klasörüne bakınız.
 */
$order = [
    'id'          => 'BENZERSIZ-SIPARIS-ID',
    'amount'      => 1.01,
    'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY, // isteğe bağlı, varsayılan: TRY
    'installment' => 0,                                    // 0 veya 1'den büyük, isteğe bağlı

    // Bazı gateway'ler tek URL kabul ettiğinden success_url ve fail_url'nin aynı olması önerilir.
    'success_url' => 'https://example.com/response.php',
    'fail_url'    => 'https://example.com/response.php',

    // Belirtilmezse config'deki dil veya varsayılan LANG_TR kullanılır.
    'lang' => \Mews\Pos\PosInterface::LANG_TR,
];

// Sipariş ve işlem tipini session'a kaydediyoruz.
// Veritabanı gibi farklı bir depolama alanı da kullanabilirsiniz.
$_SESSION['order'] = $order;
$_SESSION['tx']    = $transactionType;

// Kredi kartı nesnesi oluştur (3DHost ödemelerde gerekmez)
try {
    $card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
        $pos,
        $_POST['card_number'],
        $_POST['card_year'],
        $_POST['card_month'],
        $_POST['card_cvv'],
        $_POST['card_name'],
        $_POST['card_type'] ?? null  // kart tipi bazı gateway'lerde zorunludur
    );
} catch (\Mews\Pos\Exception\CardTypeRequiredException $e) {
    // Bu gateway için kart tipi zorunludur
    exit;
} catch (\Mews\Pos\Exception\CardTypeNotSupportedException $e) {
    // Sağlanan kart tipi bu gateway tarafından desteklenmiyor
    exit;
}

if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
    // Bu gateway ödemeyi tamamlarken tekrar kart bilgisine ihtiyaç duyar.
    $_SESSION['card'] = $_POST;
}

// 3D form verisi oluştur
try {
    $formData = $pos->get3DFormData(
        $order,
        $paymentModel,
        $transactionType,
        $card
    );
} catch (\InvalidArgumentException $e) {
    // Kart bilgisi eksik gibi geçersiz argüman durumunda fırlatılır.
    var_dump($e);
    exit;
} catch (\LogicException $e) {
    // Ödeme modeli veya işlem tipi desteklenmiyorsa fırlatılır.
    var_dump($e);
    exit;
} catch (\Throwable $e) {
    var_dump($e);
    exit;
}

// Seçilen akış tipine göre ($flowType) uygun kodu çalıştırıyoruz.
$flowType = 'by_iframe' // veya by_popup_window;

// iFrame veya popup window için $formData'yı hazırla
$gatewayUrl   = null;
$renderedForm = null;

if (is_string($formData)) {
    // Gateway hazır HTML formu döndü
    $renderedForm = $formData;
} elseif ($formData['method'] === 'GET' && $formData['inputs'] === []) {
    // Doğrudan yönlendirme (bazı gateway'ler)
    $gatewayUrl = $formData['gateway'];
} else {
    // Standart POST formu — iframe/popup içinde otomatik submit edilecek ayrı bir HTML sayfasına sarıyoruz.
    // Bu sayfa içinde formu JS ile otomatik submit eder; kullanıcı banka gateway'ine yönlendirilir.
    // Kaynak: examples/_templates/_redirect_iframe_or_popup_window_form.php
    ob_start();
    include '../../_templates/_redirect_iframe_or_popup_window_form.php';
    $renderedForm = ob_get_clean();
}
?>

<!--
    Ödeme sonucu alanı — response.php'den gelen mesaj burada gösterilir.
    Örnek Bootstrap 5 kullanmaktadır.
-->
<div class="alert alert-dismissible" role="alert" id="result-alert" style="display:none;"></div>
<pre id="result-response"></pre>

<script>
    let messageReceived = false;

    /**
     * response.php'den gelen ödeme sonucunu sayfada gösterir.
     * Mesaj verisi base64 ile kodlanmış JSON içerir.
     */
    function displayResponse(event) {
        const data     = JSON.parse(atob(event.data));
        const alertBox = document.getElementById('result-alert');

        document.getElementById('result-response')
            .appendChild(document.createTextNode(JSON.stringify(data, null, '\t')));

        if (data.status === 'approved') {
            alertBox.classList.add('alert-info');
            alertBox.textContent = 'Ödeme başarılı';
        } else {
            alertBox.classList.add('alert-danger');
            alertBox.textContent = 'Ödeme başarısız: ' + (data.error_message ?? data.md_error_message ?? '');
        }

        alertBox.style.display = 'block';
    }
</script>
```

---

#### Yöntem 1: Modal Box içinde iFrame

```php
<?php if ($flowType === 'by_iframe'): ?>
```

```html
<!-- Bootstrap 5 Modal — içine iFrame yerleştirilir -->
<div class="modal fade" id="iframe-modal" tabindex="-1" role="dialog"
     data-bs-keyboard="false" data-bs-backdrop="static">
    <div class="modal-dialog" role="document" style="width: 426px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="iframe-modal-body"></div>
        </div>
    </div>
</div>

<script>
    /**
     * response.php, iFrame içinde çalışır ve window.parent.postMessage() ile
     * ödeme sonucunu bu ana pencereye iletir.
     * Aşağıdaki listener bu mesajı yakalar, sonucu gösterir ve modal'ı kapatır.
     */
    window.addEventListener('message', function (event) {
        messageReceived = true;
        displayResponse(event);
        bootstrap.Modal.getInstance(document.getElementById('iframe-modal')).hide();
    });

    const iframeSrc    = <?= json_encode($gatewayUrl); ?>;
    const iframeSrcdoc = <?= json_encode($renderedForm); ?>;

    // iFrame oluştur ve modal body'ye ekle
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'height:500px; width:410px; border:none;';

    if (iframeSrc !== null) {
        iframe.src    = iframeSrc;     // GET ile açılan gateway URL'i
    } else {
        iframe.srcdoc = iframeSrcdoc;  // POST formunu içeren hazır HTML
    }

    document.getElementById('iframe-modal-body').appendChild(iframe);

    // Modal'ı aç
    const modalElement = document.getElementById('iframe-modal');
    const myModal      = new bootstrap.Modal(modalElement, { keyboard: false });
    myModal.show();

    // Kullanıcı ödemeyi tamamlamadan modal'ı kapatırsa uyar
    modalElement.addEventListener('hidden.bs.modal', function () {
        if (!messageReceived) {
            const alertBox = document.getElementById('result-alert');
            alertBox.classList.add('alert-danger');
            alertBox.textContent    = 'Ödeme tamamlanmadan modal kapatıldı.';
            alertBox.style.display  = 'block';
        }
    });
</script>
```

```php
<?php endif; ?>
```

---

#### Yöntem 2: Popup Window

```php
<?php elseif ($flowType === 'by_popup_window'): ?>
```

```html
<script>
    const gatewayUrl   = <?= json_encode($gatewayUrl); ?>;
    const renderedForm = <?= json_encode($renderedForm); ?>;

    // Popup'un gösterileceği URL:
    // - Gateway doğrudan GET URL sağlamışsa o URL kullanılır.
    // - Aksi hâlde POST formunu içeren HTML, Blob URL'e dönüştürülür.
    const popupUrl = gatewayUrl !== null
        ? gatewayUrl
        : URL.createObjectURL(new Blob([renderedForm], { type: 'text/html' }));

    const windowWidth  = 400;
    const leftPosition = Math.round((screen.width / 2) - (windowWidth / 2));

    const popupWindow = window.open(
        popupUrl,
        'payment_popup',
        [
            'toolbar=no', 'scrollbars=no', 'location=no',
            'statusbar=no', 'menubar=no', 'resizable=no',
            'width=' + windowWidth, 'height=500',
            'left=' + leftPosition, 'top=234'
        ].join(',')
    );

    if (popupWindow === null) {
        // Tarayıcı popup'ı engelledi
        const alertBox = document.getElementById('result-alert');
        alertBox.classList.add('alert-warning');
        alertBox.textContent   = 'Tarayıcınız popup penceresini engelledi. Lütfen popup\'lara izin verin ve tekrar deneyin.';
        alertBox.style.display = 'block';
    } else {
        popupWindow.focus();

        /**
         * response.php, popup window içinde çalışır ve window.opener.postMessage() ile
         * ödeme sonucunu bu ana pencereye iletir.
         * Aşağıdaki listener bu mesajı yakalar, sonucu gösterir ve popup'ı kapatır.
         */
        window.addEventListener('message', function (event) {
            messageReceived = true;
            displayResponse(event);
            popupWindow.close();
        });

        // Kullanıcı ödemeyi tamamlamadan popup'ı kapatırsa uyar
        const closeInterval = setInterval(function () {
            if (popupWindow.closed && !messageReceived) {
                clearInterval(closeInterval);
                const alertBox = document.getElementById('result-alert');
                alertBox.classList.add('alert-danger');
                alertBox.textContent   = 'Ödeme tamamlanmadan popup kapatıldı.';
                alertBox.style.display = 'block';
            }
        }, 1000);
    }
</script>
```

```php
<?php endif; ?>
```

---

### response.php

Banka yönlendirmesinden sonra çalışır. iFrame ve popup window için ayrı `postMessage` hedefleri kullanılır:

- **iFrame**: `window.parent` → iFrame'i barındıran ana pencere
- **Popup**: `window.opener` → popup'ı açan ana pencere

```php
<?php

require 'config.php';

$order = $_SESSION['order'];
$card  = null;

if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
    // Bu gateway ödemeyi tamamlarken tekrar kart bilgisine ihtiyaç duyar.
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

// Bazı gateway'ler callback verisini GET ile gönderir
$gatewayResponseData = (get_class($pos) === \Mews\Pos\Gateway\PayFlexCPV4Pos::class)
    ? $_GET
    : $_POST;

try {
    $response = $pos->payment(
        $paymentModel,
        $order,
        $transactionType,
        $card,
        $gatewayResponseData
    );
} catch (\Mews\Pos\Exception\HashMismatchException $e) {
    /**
     * Bankadan gelen verinin imzası geçersiz olduğunda fırlatılır.
     * Banka API bilgileriniz hatalıysa da oluşabilir.
     * Kütüphaneden kaynaklanan bir sorun varsa issue açınız.
     * Geçici çözüm: config'de disable_3d_hash_check: true (güvenlik açığı yaratır, önerilmez).
     */
    var_dump($e);
    exit;
}
?>

<script>
    /**
     * response.php, iFrame veya popup window içinde çalışır.
     * Ödeme sonucunu window arası mesajlaşma (postMessage) ile ana pencereye iletiyoruz.
     *
     * - window.parent !== window  → iFrame içinde çalışıyoruz
     * - window.opener !== null    → popup window içinde çalışıyoruz
     */
    const payload = '<?= base64_encode(json_encode($response)); ?>';

    if (window.parent !== window) {
        // iFrame: ana pencereye mesaj gönder
        window.parent.postMessage(payload, '*');
    } else if (window.opener !== null) {
        // Popup window: açan pencereye mesaj gönder
        window.opener.postMessage(payload, '*');
    }
</script>
```
