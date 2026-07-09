<?php

$templateTitle = 'Order History';

/** @var \Mews\Pos\PosInterface $pos */

$transaction = \Mews\Pos\PosInterface::TX_TYPE_ORDER_HISTORY;

require '../../_templates/_header.php';


/**
 * Ödeme tarihçesini sorgulama işlemi için gereken istek verileri Gateway'den gateway'e değiştigine göre,
 * Bu method verilen gateway göre istek verilerini oluşturur.
 *
 * @param class-string<\Mews\Pos\PosInterface> $gatewayClass
 * @param array<string, mixed> $lastResponse ödeme işlemi sonrası Pos kütüphanesinden dönen response verisi
 *
 * @return array<string, mixed>
 */
function createOrderHistoryOrder(string $gatewayClass, array $lastResponse): array
{
    $order = [];
    if (
        \Mews\Pos\Gateway\AssecoPos::class === $gatewayClass
        || \Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass
        || \Mews\Pos\Gateway\PayForPos::class === $gatewayClass
    ) {
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
        }
    } elseif (\Mews\Pos\Gateway\ToslaPos::class === $gatewayClass) {
        $order = [
            'id'               => $lastResponse['order_id'],
            'transaction_date' => $lastResponse['transaction_time'], // odeme tarihi
            'page'             => 1, // optional, default: 1
            'page_size'        => 10, // optional, default: 10
        ];
    } elseif (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
        $order = [
            'id'       => $lastResponse['order_id'],
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
    } elseif (\Mews\Pos\Gateway\VakifKatilimPos::class === $gatewayClass) {
        /** @var \DateTimeImmutable $txTime */
        $txTime = $lastResponse['transaction_time'];
        $order  = [
            'auth_code'  => $lastResponse['auth_code'],
            /**
             * Tarih aralığı maksimum 90 gün olabilir.
             */
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
        if (isset($lastResponse['transaction_id'])) {
            $order['transaction_id'] = $lastResponse['transaction_id'];
        }
    }

    return $order;
}

$lastResponse = $_SESSION['last_response'] ?? null;

$order = createOrderHistoryOrder($pos::class, $lastResponse);
dump($order);

try {
    $response = $pos->orderHistory($order);
} catch (Exception $e) {
    dd($e);
}

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
