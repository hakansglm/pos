<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\PosInterface;

trait PaymentTestTrait
{
    private function createPaymentOrder(
        string $paymentModel,
        string $currency = PosInterface::CURRENCY_TRY,
        float  $amount = 10.01,
        int    $installment = 0,
        bool   $tekrarlanan = false
    ): array {
        if ($tekrarlanan && $this->pos instanceof \Mews\Pos\Gateway\AkbankPos) {
            // AkbankPos'ta recurring odemede orderTrackId/orderId en az 36 karakter olmasi gerekiyor
            $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 28));
        } else {
            $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));
        }

        $order = [
            'id'          => $orderId,
            'amount'      => $amount,
            'currency'    => $currency,
            'installment' => $installment,
            'ip'          => '127.0.0.1',
        ];

        if ($this->pos instanceof \Mews\Pos\Gateway\ParamPos
            || \in_array($paymentModel, [
                PosInterface::MODEL_3D_SECURE,
                PosInterface::MODEL_3D_PAY,
                PosInterface::MODEL_3D_HOST,
                PosInterface::MODEL_3D_PAY_HOSTING,
            ], true)) {
            $order['success_url'] = 'http://localhost/response.php';
            $order['fail_url']    = 'http://localhost/response.php';
        }

        if ($this->pos instanceof IyzicoPos) {
            $order = array_merge($order, $this->getIyzicoOrderExtraFields());
        } elseif ($this->pos instanceof \Mews\Pos\Gateway\KuveytPos) {
            $order = array_merge($order, $this->getKuveytPosSpecificOrderFields());
        } elseif ($this->pos instanceof PayTrPos) {
            $order = array_merge($order, $this->getPayTrPosSpecificOrderFields());
        }

        if ($tekrarlanan) {
            // Desteleyen Gatewayler: GarantiPos, AssecoPos, PayFlexV4

            $order['installment'] = 0; // Tekrarlayan ödemeler taksitli olamaz.

            $recurringFrequency     = 3;
            $recurringFrequencyType = 'MONTH'; // DAY|WEEK|MONTH|YEAR
            $endPeriod              = $installment * $recurringFrequency;

            $order['recurring'] = [
                'frequency'     => $recurringFrequency,
                'frequencyType' => $recurringFrequencyType,
                'installment'   => $installment,
                'startDate'     => new \DateTimeImmutable(), // GarantiPos optional
                'endDate'       => (new \DateTime())->modify(\sprintf('+%d %s', $endPeriod, $recurringFrequencyType)), // Sadece PayFlexV4'te zorunlu
            ];
        }

        return $order;
    }

    private function createPostPayOrder(string $gatewayClass, array $lastResponse, ?float $postAuthAmount = null): array
    {
        $postAuth = [
            'id'              => $lastResponse['order_id'],
            'amount'          => $postAuthAmount ?? $lastResponse['amount'],
            'pre_auth_amount' => $lastResponse['amount'],
            'currency'        => $lastResponse['currency'],
            'ip'              => '127.0.0.1',
        ];

        if (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
            $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
        }

        if (\Mews\Pos\Gateway\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateway\PosNetPos::class === $gatewayClass) {
            $postAuth['installment'] = $lastResponse['installment_count'];
            $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
        }

        if (\Mews\Pos\Gateway\PayFlexV4Pos::class === $gatewayClass || IyzicoPos::class === $gatewayClass) {
            $postAuth['transaction_id'] = $lastResponse['transaction_id'];
        }

        return $postAuth;
    }

    private function createStatusOrder(string $gatewayClass, array $lastResponse): array
    {
        if ([] === $lastResponse) {
            throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
        }

        $statusOrder = [
            'id'       => $lastResponse['order_id'], // MerchantOrderId
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
        if (\Mews\Pos\Gateway\IyzicoPos::class === $gatewayClass && isset($lastResponse['transaction_id'])) {
            $statusOrder['transaction_id'] = $lastResponse['transaction_id'];
        }

        if (\Mews\Pos\Gateway\KuveytPos::class === $gatewayClass) {
            $statusOrder['remote_order_id'] = $lastResponse['remote_order_id']; // OrderId
        }

        if (\Mews\Pos\Gateway\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateway\PosNetPos::class === $gatewayClass) {
            /**
             * payment_model:
             * siparis olusturulurken kullanilan odeme modeli
             * orderId'yi dogru sekilde formatlamak icin zorunlu.
             */
            $statusOrder['payment_model'] = $lastResponse['payment_model'];
        }

        if (!isset($lastResponse['recurring_id'])) {
            return $statusOrder;
        }

        if (\Mews\Pos\Gateway\AssecoPos::class === $gatewayClass) {
            // tekrarlanan odemenin durumunu sorgulamak icin:
            return [
                // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
                'recurringId' => $lastResponse['recurring_id'],
            ];
        }

        return $statusOrder;
    }

    private function createCancelOrder(string $gatewayClass, array $lastResponse): array
    {
        if ([] === $lastResponse) {
            throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
        }

        $cancelOrder = [
            'id'          => $lastResponse['order_id'], // MerchantOrderId
            'currency'    => $lastResponse['currency'],
            'ref_ret_num' => $lastResponse['ref_ret_num'],
            'ip'          => '127.0.0.1',
        ];

        if (\Mews\Pos\Gateway\GarantiPos::class === $gatewayClass) {
            $cancelOrder['amount'] = $lastResponse['amount'];
        } elseif (\Mews\Pos\Gateway\ParamPos::class === $gatewayClass) {
            $cancelOrder['amount'] = $lastResponse['amount'];
            // on otorizasyon islemin iptali icin PosInterface::TX_TYPE_PAY_PRE_AUTH saglanmasi gerekiyor
            $cancelOrder['transaction_type'] = $lastResponse['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH;
        } elseif (\Mews\Pos\Gateway\KuveytPos::class === $gatewayClass) {
            $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
            $cancelOrder['auth_code']       = $lastResponse['auth_code'];
            $cancelOrder['transaction_id']  = $lastResponse['transaction_id'];
            $cancelOrder['amount']          = $lastResponse['amount'];
        } elseif (VakifKatilimPos::class === $gatewayClass) {
            $cancelOrder['remote_order_id']  = $lastResponse['remote_order_id']; // banka tarafındaki order id
            $cancelOrder['amount']           = $lastResponse['amount'];
            $cancelOrder['transaction_type'] = $lastResponse['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH;
        } elseif (\Mews\Pos\Gateway\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateway\PayFlexCPV4Pos::class === $gatewayClass) {
            // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
            $cancelOrder['transaction_id'] = $lastResponse['transaction_id'];
        } elseif (IyzicoPos::class === $gatewayClass) {
            $cancelOrder['transaction_id'] = $lastResponse['transaction_id'];
        } elseif (\Mews\Pos\Gateway\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateway\PosNetPos::class === $gatewayClass) {
            /**
             * payment_model:
             * siparis olusturulurken kullanilan odeme modeli
             * orderId'yi dogru şekilde formatlamak icin zorunlu.
             */
            $cancelOrder['payment_model'] = $lastResponse['payment_model'];
            // satis islem disinda baska bir islemi (Ön Provizyon İptali, Provizyon Kapama İptali, vs...) iptal edildiginde saglanmasi gerekiyor
            // $cancelOrder['transaction_type'] = $lastResponse['transaction_type'],
        }

        if (!isset($lastResponse['recurring_id'])) {
            return $cancelOrder;
        }

        if (\Mews\Pos\Gateway\AssecoPos::class === $gatewayClass) {
            // tekrarlanan odemeyi iptal etmek icin:
            return [
                'recurringOrderInstallmentNumber' => 1, // hangi taksidi iptal etmek istiyoruz?
            ];
        }

        return $cancelOrder;
    }

    private function createOrderHistoryOrder(string $gatewayClass, array $lastResponse): array
    {
        $order = [];
        if (AssecoPos::class === $gatewayClass || IyzicoPos::class === $gatewayClass) {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
        } elseif (\Mews\Pos\Gateway\AkbankPos::class === $gatewayClass) {
            if (isset($lastResponse['recurring_id'])) {
                $order = [
                    'id'           => $lastResponse['order_id'],
                    'recurring_id' => $lastResponse['recurring_id'],
                ];
            } else {
                $order = [
                    'id' => $lastResponse['order_id'],
                ];
            }
        } elseif (ToslaPos::class === $gatewayClass) {
            $order = [
                'id'               => $lastResponse['order_id'],
                'transaction_date' => $lastResponse['transaction_time'], // odeme tarihi
                'page'             => 1, // optional, default: 1
                'page_size'        => 10, // optional, default: 10
            ];
        } elseif (PayForPos::class === $gatewayClass) {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
        } elseif (GarantiPos::class === $gatewayClass) {
            $order = [
                'id'       => $lastResponse['order_id'],
                'currency' => $lastResponse['currency'],
                'ip'       => '127.0.0.1',
            ];
        } elseif (VakifKatilimPos::class === $gatewayClass) {
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

    private function createHistoryOrder(string $gatewayClass, array $extraData, string $ip): array
    {
        $txTime = new \DateTimeImmutable();
        if (PayForPos::class === $gatewayClass) {
            return [
                // odeme tarihi
                'transaction_date' => $extraData['transaction_date'] ?? $txTime,
            ];
        }

        if (IyzicoPos::class === $gatewayClass) {
            return [
                'transaction_date' => $extraData['transaction_date'] ?? $txTime,
                'page'             => 1,
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
                'page'       => 1,
                // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
                'start_date' => $txTime->modify('-1 day'),
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
            //        $order  = [
            //            'batch_num' => 24,
            //        ];
        }

        if (\Mews\Pos\Gateway\ParamPos::class === $gatewayClass) {
            return [
                // Gün aralığı 7 günden fazla girilemez
                'start_date' => $txTime->modify('-5 hour'),
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

    private function createRefundOrder(string $gatewayClass, array $lastResponse, ?float $refundAmount = null): array
    {
        $refundOrder = [
            'id'           => $lastResponse['order_id'], // MerchantOrderId
            'amount'       => $refundAmount ?? $lastResponse['amount'],
            'order_amount' => $lastResponse['amount'],
            'currency'     => $lastResponse['currency'],
            'ref_ret_num'  => $lastResponse['ref_ret_num'],
            'ip'           => '127.0.0.1',
        ];

        if (\Mews\Pos\Gateway\KuveytPos::class === $gatewayClass) {
            $refundOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
            $refundOrder['auth_code']       = $lastResponse['auth_code'];
            $refundOrder['transaction_id']  = $lastResponse['transaction_id'];
        } elseif (VakifKatilimPos::class === $gatewayClass) {
            $refundOrder['remote_order_id']  = $lastResponse['remote_order_id']; // banka tarafındaki order id
            $refundOrder['amount']           = $lastResponse['amount'];
            $refundOrder['transaction_type'] = $lastResponse['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH;
        } elseif (\Mews\Pos\Gateway\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateway\PayFlexCPV4Pos::class === $gatewayClass) {
            // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
            $refundOrder['transaction_id'] = $lastResponse['transaction_id'];
        } elseif (IyzicoPos::class === $gatewayClass) {
            $refundOrder['transaction_id'] = $lastResponse['transaction_id'];
        } elseif (\Mews\Pos\Gateway\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateway\PosNetPos::class === $gatewayClass) {
            /**
             * payment_model:
             * siparis olusturulurken kullanilan odeme modeli
             * orderId'yi dogru şekilde formatlamak icin zorunlu.
             */
            $refundOrder['payment_model'] = $lastResponse['payment_model'];
        }

        return $refundOrder;
    }

    /**
     * @return array<string, mixed>
     */
    private function getIyzicoOrderExtraFields(): array
    {
        return [
            'buyer' => [
                'id'                   => 'BY789',
                'name'                 => 'John',
                'surname'              => 'Doe',
                'identity_number'      => '74300864791',
                'email'                => 'email@email.com',
                'gsm_number'           => '+905350000000',
                'registration_address' => 'Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1',
                'city'                 => 'Istanbul',
                'country'              => 'Turkey',
                'zip_code'             => '34732',
                'ip'                   => '127.0.0.1',
            ],
            'shipping_address' => [
                'contact_name' => 'John Doe',
                'city'         => 'Istanbul',
                'country'      => 'Turkey',
                'address'      => 'Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1',
                'zip_code'     => '34732',
            ],
            'billing_address' => [
                'contact_name' => 'John Doe',
                'city'         => 'Istanbul',
                'country'      => 'Turkey',
                'address'      => 'Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1',
                'zip_code'     => '34732',
            ],
            'basket_items' => [
                [
                    'id'        => 'BI101',
                    'name'      => 'Binocular',
                    'category1' => 'Collectibles',
                    'category2' => 'Accessories',
                    'item_type' => 'PHYSICAL',
                    'price'     => 0.3,
                ],
                [
                    'id'        => 'BI102',
                    'name'      => 'Game code',
                    'category1' => 'Game',
                    'category2' => 'Online Game Items',
                    'item_type' => 'VIRTUAL',
                    'price'     => 9.71,
                ],
            ],
        ];
    }

    private function getPayTrPosSpecificOrderFields(): array
    {
        return [
            'buyer'           => [
                'email'      => 'test@example.com',
                'name'       => 'John Doe',
                'gsm_number' => '05001234567',
            ],
            'billing_address' => [
                'address' => 'Test Sokak No:1 Istanbul',
            ],
        ];
    }

    private function getKuveytPosSpecificOrderFields(): array
    {
        return [
            /**
             * payment_channel: İşlemin yapıldığı cihaz bilgisi (DeviceData.DeviceChannel).
             * 2 karakter olmalıdır. 01-Mobil, 02-Web Browser için kullanılmalıdır.
             */
            'payment_channel' => '02',
            'buyer'           => [
                /**
                 * Email: Kullanılan kart ile ilişkili kart hamilinin iş yerinde oluşturduğu hesapta
                 * kullandığı email adresi. Maksimum 254 karakter uzunluğunda olmalıdır.
                 */
                'email'         => 'xxxxx@gmail.com',
                /**
                 * gsm_number_cc: Kart hamilinin cep telefonuna ait ülke kodu.
                 * 1-3 karakter uzunluğunda olmalıdır.
                 */
                'gsm_number_cc' => '90',
                /**
                 * gsm_number: Kart hamilinin cep telefonuna ait abone numarası.
                 * Maksimum 15 karakter uzunluğunda olmalıdır.
                 */
                'gsm_number'    => '1234567899',
            ],
            'billing_address' => [
                /**
                 * BillAddrCity: Kullanılan kart ile ilişkili kart hamilinin fatura adres şehri.
                 * Maksimum 50 karakter uzunluğunda olmalıdır.
                 */
                'city'     => 'İstanbul',
                /**
                 * BillAddrCountry: Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki ülke kodu.
                 * Maksimum 3 karakter uzunluğunda olmalıdır.
                 * ISO 3166-1 sayısal üç haneli ülke kodu standardı kullanılmalıdır.
                 */
                'country'  => '792',
                /**
                 * BillAddrLine1: Kart hamilinin fatura adresinde yer alan sokak vb. bilgileri içeren açık adresi.
                 * Maksimum 150 karakter uzunluğunda olmalıdır.
                 */
                'address'  => 'XXX Mahallesi XXX Caddesi No 55 Daire 1',
                /**
                 * BillAddrPostCode: Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki posta kodu.
                 */
                'zip_code' => '34000',
                /**
                 * BillAddrState: Kart hamilinin fatura adresindeki il veya eyalet bilgisi kodu.
                 * ISO 3166-2'de tanımlı olan il/eyalet kodu olmalıdır.
                 */
                'state'    => '34',
            ],
        ];
    }
}
