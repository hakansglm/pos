<?php

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;

/** @var string $baseUrl */
/** @var string $ip */
/** @var PosInterface::MODEL_3D_* $paymentModel */
/** @var PosInterface $pos */

/**
 * Bu kod MODEL_3D_SECURE, MODEL_3D_PAY, MODEL_3D_HOST odemeler icin gereken HTML form verisini olusturur.
 * Odeme olmayan (iade, iptal, durum) veya MODEL_NON_SECURE islemlerde kullanilmaz.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.$baseUrl.'index.php');
    exit();
}
$transaction = $_POST['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;
$order       = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $_POST['currency'] ?? PosInterface::CURRENCY_TRY,
    $_POST['installment'] ?? null,
    ($_POST['is_recurring'] ?? 0) == 1,
    $_POST['lang'] ?? PosInterface::LANG_TR
);

$_SESSION['order'] = $order;
$_SESSION['tx'] = $transaction;

if ($paymentModel !== PosInterface::MODEL_3D_HOST) {
    $card = createCard($pos, $_POST);
    if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
        // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım olacak.
        $_SESSION['card'] = $_POST;
    }
} else {
    $card = null;
}


// İsteğe bağlı: istek bankaya gönderilmeden önce düzenlemek için bu listener'ı kullanın.
// Banka özelinde örnekler için ilgili bankanın _config.php dosyasına bakınız.
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event): void {
    // $data = $event->getRequestData();
    // $data['ozel_alan'] = 'deger';
    // $event->setRequestData($data);
});

// İsteğe bağlı: 3D form hash hesaplanmadan önce form alanlarını düzenlemek için bu listener'ı kullanın.
// Banka özelinde örnekler için ilgili bankanın _config.php dosyasına bakınız.
$eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event): void {
    // $inputs = $event->getFormInputs();
    // $inputs['ozel_alan'] = 'deger';
    // $event->setFormInputs($inputs);
});

try {
    $formData = $pos->get3DFormData(
        $order,
        $paymentModel,
        $transaction,
        $card,
        /**
         * MODEL_3D_SECURE veya MODEL_3D_PAY ödemelerde kredi kart verileri olmadan
         * form verisini oluşturmak için true yapabilirsiniz.
         * Yine de bazı gatewaylerde kartsız form verisi oluşturulamıyor.
         */
        false, // default: false
        /**
         * İsteğe bağlı: 3D form verisinin dönüş formatını belirtir.
         * PosInterface::FORM_FORMAT_ARRAY: gateway URL, HTTP metodu ve form alanlarını içeren dizi döner.
         * PosInterface::FORM_FORMAT_HTML: hazır HTML form string'i döner.
         * Belirtilmezse (null) gateway'in varsayılan formatı kullanılır.
         * Desteklenmeyen format talep edilirse UnsupportedFormFormatException fırlatılır.
         *
         * PayForPos'da IP M047 IP kısıtlaması sorunu yaşarsanız PosInterface::FORM_FORMAT_HTML değeri kullanarak sorunu
         * çözebilirsiniz.
         */
        // null // $formFormat, default: null
    );
} catch (\InvalidArgumentException $e) {
    // örneğin kart bilgisi sağlanmadığında bu exception'i alırsınız.
    dd($e);
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    dd($e);
} catch (\Throwable $e) {
    dd($e);
}


// İsteğe bağlı: banka tarafından sağlanan kampanya kodları; bankadan temin edilir.
// YapıKredi (PosNetPos):  $formData['inputs']['vftCode']       = 'xxx'; // VFT Kampanya Kodu
//                         $formData['inputs']['useJokerVadaa'] = '1';   // Joker Vadaa kampanya seçimini açar
// Albaraka (PosNetV1Pos): $formData['inputs']['VftCode']       = 'xxx';
//                         $formData['inputs']['UseJokerVadaa'] = '1';
//                         $formData['inputs']['KOICode']       = 'xxx'; // 1:Ek Taksit 2:Taksit Atlatma 3:Ekstra Puan 4:Kontur Kazanım 5:Ekstre Erteleme 6:Özel Vade Farkı

$flowType = $_POST['payment_flow_type'] ?? null;

if (!('by_redirection' === $flowType && $formData['inputs'] === [] && $formData['method'] === 'GET')) {
    require '../../_templates/_header.php';
}
?>


    <!------------------------------------------------------------------------------------------------------------->
    <!--
        Alttaki kodlarda secilen islem akisina gore
            - redirect ile odeme
            - modal box'ta odeme
            - pop up window'da odeme
        gereken kodlari calistiryoruz.
        Size gereken odeme akis yontemine gore alttaki kodlari kullaniniz.
    -->
    <!------------------------------------------------------------------------------------------------------------->

<?php if ('by_redirection' === $flowType) : ?>
    <?php if (is_string($formData)) : ?>
        <?= $formData; ?>
    <?php elseif ($formData['inputs'] === [] && $formData['method'] === 'GET'):
        header('Location: '.$formData['gateway']);
    else: ?>
        <!--
        Sık kullanılan yöntem, 3D form verisini bir HTML form içine basıp JS ile otomatik submit ediyoruz.
        Submit sonucu kullanıcı banka sayfasıne yönlendirilir, işlem sonucundan ise duruma göre websitinizin
        success veya fail URL'na geri yönlendilir.
    -->
        <?php require '../../_templates/_redirect_form.php'; ?>
        <script>
            // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
            let redirectForm = document.querySelector('form.redirect-form');
            if (redirectForm) {
                redirectForm.submit();
            }
        </script>
    <?php endif; ?>
<?php elseif ('by_iframe' === $flowType || 'by_popup_window' === $flowType):
    $gatewayUrl   = null;
    $renderedForm = null;
    if (is_string($formData)) {
        $renderedForm = $formData;
    } elseif ($formData['method'] === 'GET' && $formData['inputs'] === []) {
        $gatewayUrl = $formData['gateway'];
    } else {
        ob_start();
        include('../../_templates/_redirect_iframe_or_popup_window_form.php');
        $renderedForm = ob_get_clean();
    }
    ?>
    <!--
        $renderedForm içinde 3D formun verileriyle oluşturulan HTML form bulunur.
        alttaki kodlar ise bu $renderedForm verisini seçilen $flowType'a göre iframe modal box içine veya pop up window içine basar.
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
<?php endif; ?>


<?php if ('by_iframe' === $flowType) : ?>
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


<?php elseif ('by_popup_window' === $flowType) : ?>
    <script>

        const gatewayUrl   = <?= json_encode($gatewayUrl); ?>;
        const renderedForm = <?= json_encode($renderedForm); ?>;

        windowWidth = 400;
        let leftPosition = (screen.width / 2) - (windowWidth / 2);
        let popupUrl = gatewayUrl !== null
            ? gatewayUrl
            : URL.createObjectURL(new Blob([renderedForm], {type: 'text/html'}));
        let popupWindow = window.open(
            popupUrl,
            'popup_window',
            'toolbar=no,scrollbars=no,location=no,statusbar=no,menubar=no,resizable=no,width=' + windowWidth + ',height=500,left=' + leftPosition + ',top=234'
        );
        if (null === popupWindow) {
            // pop up bloke edilmis.
            alert("pop window'a izin veriniz.");
        } else {

            // fokusu popup windowa odakla
            window.target = 'popup_window';

            /**
             * Bankadan geri websitenize yönlendirme yapıldıktan sonra ödeme sonuç verisi iframe/popup içinde olur.
             * Popup'tan ana pencereye JS'in windowlar arası Message API'ile ödeme sonucunu ana window'a gönderiyoruz.
             * Alttaki kod ise bu message API event'ni dinler,
             * message (yani bankadan dönen ödeme sonucu) aldığında sonucu kullanıcıya ana window'da gösterir
             */
            window.addEventListener('message', function (event) {
                messageReceived = true;
                displayResponse(event);
                popupWindow.close();
            });
        }
        /**
         * kullanıcı ödeme işlemine devam etmeden popup window'u kapatabilir.
         * Burda o durumu kontrol ediyoruz.
         */
        let closeInterval = setInterval(function () {
            if (popupWindow.closed && !messageReceived) {
                // window is closed without completing payment
                clearInterval(closeInterval);
                let alertBox = document.getElementById('result-alert');
                alertBox.classList.add('alert-danger');
                alertBox.appendChild(document.createTextNode('popup kapatildi'));
                alertBox.style.display = 'block';
            }
        }, 1000);
    </script>
<?php endif; ?>
<?php
require '../../_templates/_footer.php';
