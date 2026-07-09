<?php

use Mews\Pos\PosInterface;

require '_config.php';
/** @var \Mews\Pos\PosInterface $pos */
/** @var string $baseUrl */
/** @var string $ip */
/** @var \Mews\Pos\PosInterface::MODEL_* $paymentModel */


$transaction = $_POST['tx'] ?? PosInterface::TX_TYPE_PAY_AUTH;
$order       = createPaymentOrder(
    $pos,
    $paymentModel,
    $baseUrl,
    $ip,
    $_POST['currency'] ?? PosInterface::CURRENCY_TRY,
    $_POST['installment'] ?? 0,
    ($_POST['is_recurring'] ?? 0) == 1,
    $_POST['lang'] ?? PosInterface::LANG_TR
);
$_SESSION['order'] = $order;
$_SESSION['tx'] = $transaction;

try {
    $formData = $pos->get3DFormData(
        $order,
        PosInterface::MODEL_NON_SECURE, // @phpstan-ignore-line argument.type
        $transaction
    );

    \assert(\is_array($formData));
    unset($formData['inputs']['Rnd']);
    unset($formData['inputs']['Hash']);
    $formData['inputs']['UserPass'] = $pos->getAccount()->getPassword();
    $formData['gateway'] = 'https://vpostest.qnb.com.tr/Gateway/QR/QRHost.aspx';

} catch (\InvalidArgumentException $e) {
    // örneğin kart bilgisi sağlanmadığında bu exception'i alırsınız.
    dd($e);
} catch (\LogicException $e) {
    // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
    dd($e);
} catch (\Throwable $e) {
    dd($e);
}

require '../../_templates/_redirect_form.php'; ?>
<script>
    // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
    // let redirectForm = document.querySelector('form.redirect-form');
    // if (redirectForm) {
    //     redirectForm.submit();
    // }
</script>
