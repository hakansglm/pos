<?php

$templateTitle = 'Cancel Order';

/** @var \Mews\Pos\PosInterface $pos */
/** @var string $ip */

$transaction = \Mews\Pos\PosInterface::TX_TYPE_CANCEL;

require '../../_templates/_header.php';

/**
 * İptal işlemi için gereken istek verileri Gateway'den gateway'e değiştigine göre,
 * Bu method verilen gateway göre istek verilerini oluşturur.
 *
 * @param class-string<\Mews\Pos\PosInterface> $gatewayClass
 * @param array<string, mixed> $lastResponse ödeme işlemi sonrası Pos kütüphanesinden dönen response verisi
 * @param string $ip
 *
 * @return array<string, mixed>
 */
function createCancelOrder(string $gatewayClass, array $lastResponse, string $ip): array
{
    $cancelOrder = [
        'id'          => $lastResponse['order_id'], // MerchantOrderId
        'currency'    => $lastResponse['currency'],
        'ref_ret_num' => $lastResponse['ref_ret_num'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
        $cancelOrder['amount'] = $lastResponse['amount'];
    } elseif (\Mews\Pos\Gateway\ParamPos::class === $gatewayClass) {
        $cancelOrder['amount'] = $lastResponse['amount'];
        // on otorizasyon islemin iptali icin PosInterface::TX_TYPE_PAY_PRE_AUTH saglanmasi gerekiyor
        $cancelOrder['transaction_type'] = $lastResponse['transaction_type'] ?? \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;
    } elseif (\Mews\Pos\Gateway\KuveytPos::class === $gatewayClass) {
        $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $cancelOrder['auth_code']       = $lastResponse['auth_code'];
        $cancelOrder['transaction_id']  = $lastResponse['transaction_id'];
        $cancelOrder['amount']          = $lastResponse['amount'];
    } elseif (\Mews\Pos\Gateway\VakifKatilimPos::class === $gatewayClass) {
        $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $cancelOrder['amount']          = $lastResponse['amount'];
        // on otorizasyon islemin iptali icin PosInterface::TX_TYPE_PAY_PRE_AUTH saglanmasi gerekiyor
        $cancelOrder['transaction_type'] = $lastResponse['transaction_type'] ?? \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;
    } elseif (\Mews\Pos\Gateway\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateway\PayFlexCPV4Pos::class === $gatewayClass) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $cancelOrder['transaction_id'] = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass) {
        $cancelOrder['transaction_id'] = $lastResponse['transaction_id'];
    }  elseif (\Mews\Pos\Gateway\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateway\PosNetPos::class === $gatewayClass) {
        /**
         * payment_model: siparis olusturulurken kullanilan odeme modeli.
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $cancelOrder['payment_model'] = $lastResponse['payment_model'];
        // satis islem disinda baska bir islemi (Ön Provizyon İptali, Provizyon Kapama İptali, vs...) iptal edildiginde saglanmasi gerekiyor
        // $cancelOrder['transaction_type'] = $lastResponse['transaction_type'],
    }


    if (isset($lastResponse['recurring_id'])) {
        // tekrarlanan odemeyi iptal etmek icin:
        if (\Mews\Pos\Gateway\AssecoPos::class === $gatewayClass) {
            $cancelOrder += [
                'recurringOrderInstallmentNumber' => 1, // hangi taksidi iptal etmek istiyoruz?
            ];
        } elseif (\Mews\Pos\Gateway\AkbankPos::class === $gatewayClass) {
            // Henüz tahsil edilmemiş bir taksiti iptal etmek için:
            // Tahsil edilmiş taksit için 'recurring_payment_is_pending' => false kullanın.
            // Tüm bekleyen taksitleri iptal etmek için 'recurringOrderInstallmentNumber' => null kullanın.
            $cancelOrder += [
                'recurring_id'                    => $lastResponse['recurring_id'],
                'recurringOrderInstallmentNumber' => 2,
                'recurring_payment_is_pending'    => true,
            ];
        }
    }

    return $cancelOrder;
}


$order = createCancelOrder($pos::class, $_SESSION['last_response'] ?? null, $ip);
dump($order);

try {
    $response = $pos->cancel($order);
} catch (Exception $e) {
    dd($e);
}

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
