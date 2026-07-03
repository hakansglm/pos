<?php

use Mews\Pos\PosQuery\PosQueryInterface;

$templateTitle = 'History Request';

require '_config.php';
$transaction = PosQueryInterface::QUERY_TYPE_HISTORY;

require '../../_templates/_header.php';

function createHistoryOrder(string $gatewayClass, string $ip): array
{
    $txTime = new \DateTimeImmutable();

    if (\Mews\Pos\Gateway\PayForPos::class === $gatewayClass) {
        return [
            'transaction_date' => $txTime,
        ];
    }

    if (\Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass) {
        return [
            'transaction_date' => $txTime,
            'page'             => 1,
        ];
    }

    if (\Mews\Pos\Gateway\VakifKatilimPos::class === $gatewayClass) {
        return [
            'page'       => 1,
            'page_size'  => 20,
            // Tarih aralığı maksimum 90 gün olabilir.
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    if (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
        return [
            'ip'         => $ip,
            'currency'   => \Mews\Pos\PosInterface::CURRENCY_USD,
            'page'       => 1, // optional
            // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
            'start_date' => $txTime,
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    if (\Mews\Pos\Gateway\AkbankPos::class === $gatewayClass) {
        return [
            // Gün aralığı 1 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,
        ];
//        ya da batch number ile (batch number odeme isleminden alinan response'da bulunur):
//        return [
//            'batch_num' => 396,
//        ];
    }

    if (\Mews\Pos\Gateway\ParamPos::class === $gatewayClass) {
        return [
            // Gün aralığı 7 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,

            // optional:
//            'transaction_type' => \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH,
//            'order_status' => 'Başarılı',
        ];
    }

    if (\Mews\Pos\Gateway\PayTrPos::class === $gatewayClass) {
        return [
            // Maksimum 3 günlük tarih aralığı
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime,
        ];
    }

    return [];
}

$order = createHistoryOrder($posClass, $ip);
dump($order);

try {
    $response = $posQuery->history($order);
} catch (Exception $e) {
    dd($e);
}

dump($response);
require '../../_templates/_footer.php';
