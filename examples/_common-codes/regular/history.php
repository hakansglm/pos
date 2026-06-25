<?php

$templateTitle = 'History Request';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';
$transaction = \Mews\Pos\PosInterface::TX_TYPE_HISTORY;

require '../../_templates/_header.php';

function createHistoryOrder(string $gatewayClass, array $extraData, string $ip): array
{
    $txTime = new \DateTimeImmutable();
    if (\Mews\Pos\Gateway\PayForPos::class === $gatewayClass) {
        return [
            // odeme tarihi
            'transaction_date' => $extraData['transaction_date'] ?? $txTime,
        ];
    }
    if (\Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass) {
        return [
            // odeme tarihi
            'transaction_date' => $extraData['transaction_date'] ?? $txTime,
            //'transaction_date' => $extraData['transaction_date'] ?? $txTime->modify('-3 days'),
            'page'       => 1,
        ];
    }


    if (\Mews\Pos\Gateway\VakifKatilimPos::class === $gatewayClass) {
        return [
            'page'       => 1,
            'page_size'  => 20,
            /**
             * Tarih aralığı maksimum 90 gün olabilir.
             */
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    if (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
        return [
            'ip'         => $ip,
            'currency'   => \Mews\Pos\PosInterface::CURRENCY_USD,
            'page'       => 1, //optional
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
            // Bu değerler gönderilince API nedense hata veriyor.
//            'transaction_type' => \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, // TX_TYPE_CANCEL, TX_TYPE_REFUND
//            'order_status' => 'Başarılı', // Başarılı, Başarısız
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

$order = createHistoryOrder(get_class($pos), [], $ip);
dump($order);

try {
    $response = $pos->history($order);
} catch (Exception $e) {
    dd($e);
}

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
