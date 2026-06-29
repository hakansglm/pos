<?php

use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\PosInterface;

// ilgili gatewayin payment modele gore configini load ediyoruz
// ornegin: asseco/3d/_config.php ya da asseco/3d-host/_config.php
require_once '_config.php';

/**
 * alttaki script
 * MODEL_3D_SECURE, MODEL_3D_PAY, MODEL_3D_HOST odeme modellerde ve TX_TYPE_PAY_AUTH, TX_TYPE_PAY_PRE_AUTH islem
 * tiplerinde gatewayden geri websitenize yonlendirildiginde calisir.
 *
 * Bu script redirectli, iframe'de ve popup'da odemeler icin kullanilabilinir.
 */
// 3D odemelerde gatewayden genelde POST istek bekleniyor.
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ('GET' === $requestMethod) { // PayFlexCP ve PayTr GET request ile cevaplıyor.
   if (get_class($pos) === \Mews\Pos\Gateway\PayTrPos::class) {
       // PayTr başarılı durumda hiç bir veri göndermiyor.
       // Yine de ödeme tamamlanmamış oluyor. Bu yüzden alttaki kodlar çalışmamaı gerekiyor.
       header('Location: '.$baseUrl);
       exit();
       // Bildirim URL'a gelecek sonucu beklememiz gerekiyor.
       // Başarısız durumda ise $_POST verisi gönderir.
   } elseif (get_class($pos) !== \Mews\Pos\Gateway\PayFlexCPV4Pos::class) {
       // Diğer gatewaylerde GET istek ile geçersiz olduğu için alttaki kodlar çalışmaması gerekiyor.
       header('Location: '.$baseUrl);
       exit();
   }
}

if (get_class($pos) !== \Mews\Pos\Gateway\PayTrPos::class) {
    $order = $_SESSION['order'] ?? null;
    if (!$order) {
        throw new Exception('Sipariş bulunamadı, session sıfırlanmış olabilir.');
    }
}  else {
    // PayTR callback URL'a istek gönderdi, sunucular arasında iletişim olduğu için
    // session yok veya boş.
    $order = [];
}

// İsteğe bağlı: istek bankaya gönderilmeden önce düzenlemek için bu listener'ı kullanın.
// Banka özelinde örnekler için ilgili bankanın _config.php dosyasına bakınız.
/** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
$eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event): void {
    // $data = $event->getRequestData();
    // $data['ozel_alan'] = 'deger';
    // $event->setRequestData($data);
});

$card = null;
if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
    // bu gateway ödemeyi tamamlarken tekrar kart bilgisi gerektiriyor.
    $savedCard = $_SESSION['card'] ?? null;
    if (isset($_SESSION['card'])) {
        unset($_SESSION['card']);
    }
    $card = createCard($pos, $savedCard);
}

try {
    $response = doPayment($pos, $paymentModel, $transaction, $order, $card);
} catch (HashMismatchException $e) {
    /**
     * Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
     * Veya Banka API bilgileriniz hatalı ise de oluşur.
     * Eğer kütühaneden dolayı hash doğrulama hatası alıyorsanız, issue oluşturunuz.
     * Issue çözülene kadar geçici olarak disable_3d_hash_check: true ayarla hash doğrulamasını devre dışı bırakabilirsiniz.
     * Güvenlik açısından disable_3d_hash_check: false olarak kullanılması tavsiye edilmez.
     */
    dd($e);
} catch (\Exception|\Error $e) {
    dd($e);
}
if (get_class($pos) === \Mews\Pos\Gateway\PayTrPos::class) {
    /**
     * PayTR callback (Bildirim) URL'e response'u gönderdi.
     * Cevap olarak "OK" göndermemiz gerekiyor.
     * NOT: PayTR "OK" cevabı alıncaya kadar aynı ödeme işlemi için Bildirim URL birden fazla kez call eder.
     */
    echo 'OK';
    exit;
}
if ($pos->isSuccess()) {
    $_SESSION['last_response'] = $response;
}
require '../../_templates/_header.php';
require __DIR__.'/_render_payment_response.php';
?>

<script>
    if (window.opener && window.opener !== window) {
        // you are in a popup
        // send result data to parent window
        window.opener.parent.postMessage(`<?= base64_encode(json_encode($response)); ?>`);
    } else if (window.parent) {
        // you are in iframe
        // send result data to parent window
        window.parent.postMessage(`<?= base64_encode(json_encode($response)); ?>`);
    }
</script>
<?php require __DIR__.'/_footer.php'; ?>
