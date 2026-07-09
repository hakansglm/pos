<?php

use Mews\Pos\PosInterface;

/** @var \Mews\Pos\PosInterface $pos */
/** @var string $ip */

$templateTitle = 'Post Auth Order (ön provizyonu kapama)';

/**
 * Ön provizyon kapama işlemi için gereken istek verileri Gateway'den gateway'e değiştigine göre,
 * bu method verilen gateway göre istek verilerini oluşturur.
 *
 * @param class-string<\Mews\Pos\PosInterface> $gatewayClass
 * @param array<string, mixed> $lastResponse ön provizyon açma işlemi sonrası Pos kütüphanesinden dönen response verisi
 * @param string $ip
 * @param float|null $postAuthAmount ön provizyon başlatılan amount kapatılmak istenen amount'tan farklı olduğunda kullanilir.
 *
 * @return array<string, mixed>
 */
function createPostPayOrder(string $gatewayClass, array $lastResponse, string $ip, ?float $postAuthAmount = null): array
{
    $postAuth = [
        'id'              => $lastResponse['order_id'],
        'amount'          => $postAuthAmount ?? $lastResponse['amount'],
        'pre_auth_amount' => $lastResponse['amount'], // amount > pre_auth_amount durumlar icin kullanilir
        'currency'        => $lastResponse['currency'],
        'ip'              => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }
    if (\Mews\Pos\Gateway\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateway\PosNetPos::class === $gatewayClass) {
        $postAuth['installment'] = $lastResponse['installment_count'];
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }
    if (\Mews\Pos\Gateway\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass) {
        $postAuth['transaction_id'] = $lastResponse['transaction_id'];
    }

    return $postAuth;
}

$lastResponse = $_SESSION['last_response'] ?? null;

$preAuthAmount = $lastResponse['amount'];
// otorizasyon kapama amount'u ön otorizasyon amount'tan daha fazla olabilir.
$postAuthAmount = $lastResponse['amount'] + 0.20;
$gatewayClass = $pos::class;

$order = createPostPayOrder(
    $gatewayClass,
    $lastResponse,
    $ip,
    $postAuthAmount
);

$transaction = PosInterface::TX_TYPE_PAY_POST_AUTH;

require '../../_templates/_finish_non_secure_post_auth_payment.php';
