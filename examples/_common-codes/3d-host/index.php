<?php

use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/3d-host/_config.php
require '_config.php';

$order = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $_POST['currency'] ?? PosInterface::CURRENCY_TRY,
    $_POST['installment'] ?? 0,
    false,
    $_POST['lang'] ?? PosInterface::LANG_TR
);

$_SESSION['order'] = $order;

$formVerisiniOlusturmakIcinApiIstegiGonderenGatewayler = [
    \Mews\Pos\Gateways\PosNetPos::class,
    \Mews\Pos\Gateways\KuveytPos::class,
    \Mews\Pos\Gateways\ToslaPos::class,
    \Mews\Pos\Gateways\VakifKatilimPos::class,
    \Mews\Pos\Gateways\PayFlexV4Pos::class,
    \Mews\Pos\Gateways\PayFlexCPV4Pos::class,
];
if (in_array(get_class($pos), $formVerisiniOlusturmakIcinApiIstegiGonderenGatewayler, true)) {
    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
//        // Burda istek banka API'na gonderilmeden once gonderilecek veriyi degistirebilirsiniz.
//        // Ornek:
//        if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
//            $data         = $event->getRequestData();
//            $data['abcd'] = '1234';
//            $event->setRequestData($data);
//        }
    });
}


/**
 * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
 */
$eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event) {
//    if ($event->getGatewayClass() !== \Mews\Pos\Gateways\EstV3Pos::class || $event->getGatewayClass() !== \Mews\Pos\Gateways\EstPos::class) {
//        return;
//    }
//    // Örneğin İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
//    $supportedPaymentModels = [
//        \Mews\Pos\PosInterface::MODEL_3D_PAY,
//        \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING,
//        \Mews\Pos\PosInterface::MODEL_3D_HOST,
//    ];
//    if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
//        $formInputs           = $event->getFormInputs();
//        $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
//        $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
//        $event->setFormInputs($formInputs);
//    }
});

try {
    $formData = $pos->get3DFormData(
        $order,
        PosInterface::MODEL_3D_HOST,
        $transaction
        // null,  // $creditCard, default: null
        // false, // $createWithoutCard, default: false
        /**
         * İsteğe bağlı: 3D form verisinin dönüş formatını belirtir.
         * PosInterface::FORM_FORMAT_ARRAY: gateway URL, HTTP metodu ve form alanlarını içeren dizi döner.
         * PosInterface::FORM_FORMAT_HTML: hazır HTML form string'i döner.
         * Belirtilmezse (null) gateway'in varsayılan formatı kullanılır.
         * Desteklenmeyen format talep edilirse UnsupportedFormFormatException fırlatılır.
         */
        // null   // $formFormat, default: null
    );
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    dd($e);
} catch (\Exception $e) {
    dd($e);
}
?>
<?php if (is_string($formData)):
    require '../../_templates/_header.php';
    echo $formData;
    require '../../_templates/_footer.php';
elseif ($formData['inputs'] === [] && $formData['method'] === 'GET'):
   header('Location: '.$formData['gateway']);
else:
    require '../../_templates/_header.php';
    require '../../_templates/_redirect_form.php';
    require '../../_templates/_footer.php';
?>
    <script>
        // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
        let redirectForm = document.querySelector('form.redirect-form');
        if (redirectForm) {
            redirectForm.submit();
        }
    </script>
<?php endif; ?>
