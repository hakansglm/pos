<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\AkbankPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for AkbankPos Gateway requests
 *
 * @internal
 */
class AkbankPosRequestDataMapper extends AbstractRequestDataMapper
{
    public const API_VERSION = '1.00';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->applyPaymentDefaults($order);

        return $this->getRequestAccountData($posAccount) + [
                'version'           => self::API_VERSION,
                'txnCode'           => $this->valueMapper->mapTxType($txType, PosInterface::MODEL_NON_SECURE),
                'requestDateTime'   => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
                'randomNumber'      => $this->crypt->generateRandomString(),
                'order'             => [
                    'orderId' => (string) $order['id'],
                ],
                'transaction'       => [
                    'amount'       => $this->valueFormatter->formatAmount($order['amount']),
                    'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
                    'motoInd'      => 0,
                    'installCount' => $this->valueFormatter->formatInstallment($order['installment']),
                ],
                'secureTransaction' => [
                    'secureId'      => $responseData['secureId'],
                    'secureEcomInd' => $responseData['secureEcomInd'],
                    'secureData'    => $responseData['secureData'],
                    'secureMd'      => $responseData['secureMd'],
                ],
                'customer'          => [
                    'ipAddress' => $order['ip'],
                ],
            ];
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->applyPaymentDefaults($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->valueMapper->mapTxType($txType, PosInterface::MODEL_NON_SECURE),
                'requestDateTime' => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'card'            => [
                    'cardNumber' => $creditCard->getNumber(),
                    'cvv2'       => $creditCard->getCvv(),
                    'expireDate' => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expireDate'),
                ],
                'transaction'     => [
                    'amount'       => $this->valueFormatter->formatAmount($order['amount']),
                    'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
                    'motoInd'      => 0,
                    'installCount' => $this->valueFormatter->formatInstallment($order['installment']),
                ],
                'customer'        => [
                    'ipAddress' => $order['ip'],
                ],
            ];

        if (isset($order['recurring'])) {
            $requestData += $this->createRecurringData($order['recurring']);
            $requestData['transaction']['motoInd'] = 1;
            $requestData['order']                  = [
                'orderTrackId' => (string) $order['id'],
            ];
        } else {
            $requestData['order'] = [
                'orderId' => (string) $order['id'],
            ];
        }

        return $requestData;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        /** @var array<string, mixed> $order */
        $order = [
            'id'               => $order['id'],
            'amount'           => $order['amount'],
            'currency'         => $order['currency'],
            'ip'               => $order['ip'],
            'transaction_time' => $this->createDateTime(),
        ];

        return $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'requestDateTime' => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'order'           => [
                    'orderId' => (string) $order['id'],
                ],
                'transaction'     => [
                    'amount'       => $this->valueFormatter->formatAmount($order['amount']),
                    'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
                ],
                'customer'        => [
                    'ipAddress' => $order['ip'],
                ],
            ];
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        /** @var array<string, mixed> $order */
        $order = \array_merge($order, [
            'id'               => $order['id'] ?? null,
            'recurring_id'     => $order['recurring_id'] ?? null,
            'transaction_time' => $this->createDateTime(),
        ]);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'txnCode'         => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'version'         => self::API_VERSION,
                'requestDateTime' => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
                'randomNumber'    => $this->crypt->generateRandomString(),
            ];

        if (\array_key_exists('recurringOrderInstallmentNumber', $order)) {
            /**
             * Henüz provizyon almamış ileri tarihli talimat işlemini veya recurring işlemi iptal etmek için kullanılmaktadır.
             */
            if (null !== $order['recurringOrderInstallmentNumber']) {
                /**
                 * Recurring işlem talimatlarının tamamı iptal edilmek isteniyorsa, recurringOrder parametresi gönderilmemelidir.
                 * Recurring işlem talimatlarından sadece biri iptal edilmek isteniyorsa,
                 * ilgili talimatın recurringOrder değeri işlem isteğinde iletilmelidir.
                 */
                $requestData['recurring'] = [
                    'recurringOrder' => $order['recurringOrderInstallmentNumber'],
                ];
                if (isset($order['recurring_payment_is_pending']) && true === $order['recurring_payment_is_pending']) {
                    $requestData['txnCode'] = '1013';
                }
            } else {
                $requestData['txnCode'] = '1013';
            }

            $requestData['order'] = [
                'orderTrackId' => (string) $order['recurring_id'],
            ];
        } else {
            $requestData['order'] = [
                'orderId' => (string) $order['id'],
            ];
        }

        return $requestData;
    }

    /**
     * Eğer kısmi tutarlı iade işlemi yapılmak isteniyorsa, iade işlemi requestinde transaction alanı gönderilmelidir.
     * Eğer transaction alanı gönderilmezse, iade işlemi tam tutarlı olarak gerçekleşecektir.
     *
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        /** @var array<string, mixed> $order */
        $order = \array_merge($order, [
            'id'               => $order['id'] ?? null,
            'recurring_id'     => $order['recurring_id'] ?? null,
            'currency'         => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'           => $order['amount'],
            'transaction_time' => $this->createDateTime(),
        ]);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->valueMapper->mapTxType($refundTxType),
                'requestDateTime' => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'transaction'     => [
                    'amount'       => $this->valueFormatter->formatAmount($order['amount']),
                    'currencyCode' => $this->valueMapper->mapCurrency($order['currency']),
                ],
            ];

        if (isset($order['recurringOrderInstallmentNumber'])) {
            /**
             * Provizyon almış Recurring ve/veya İleri tarihli Satış işlemlerinin iadesi yapılabilir.
             * Ön Provizyon işlemlerinin iadesi yapılamamaktadır.
             */
            $requestData['recurring'] = [
                'recurringOrder' => $order['recurringOrderInstallmentNumber'],
            ];

            $requestData['order'] = [
                'orderTrackId' => (string) $order['recurring_id'],
            ];
        } else {
            $requestData['order'] = [
                'orderId' => (string) $order['id'],
            ];
        }

        return $requestData;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = \array_merge($order, [
            'id'               => $order['id'] ?? null,
            'recurring_id'     => $order['recurring_id'] ?? null,
            'transaction_time' => $this->createDateTime(),
        ]);

        $result = $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_ORDER_HISTORY, PosInterface::MODEL_NON_SECURE),
                'requestDateTime' => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'order'           => [],
            ];

        if (isset($order['recurring_id'])) {
            $result['order']['orderTrackId'] = $order['recurring_id'];
        } else {
            $result['order']['orderId'] = $order['id'];
        }

        return $result;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     */
    public function create3DFormData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        string               $gatewayURL,
        ?CreditCardInterface $creditCard = null,
        ?array               $extraData = null
    ): array {
        $order = $this->applyPaymentDefaults($order);

        $inputs = [
            'paymentModel'    => $this->valueMapper->mapSecureType($paymentModel),
            'txnCode'         => $this->valueMapper->mapTxType($txType, $paymentModel),
            'merchantSafeId'  => $posAccount->getMerchantId(),
            'terminalSafeId'  => $posAccount->getTerminalId(),
            'orderId'         => (string) $order['id'],
            'lang'            => $this->getLang($order),
            'amount'          => (string) $this->valueFormatter->formatAmount($order['amount']),
            'currencyCode'    => (string) $this->valueMapper->mapCurrency($order['currency']),
            'installCount'    => (string) $this->valueFormatter->formatInstallment($order['installment']),
            'okUrl'           => (string) $order['success_url'],
            'failUrl'         => (string) $order['fail_url'],
            'randomNumber'    => $this->crypt->generateRandomString(),
            'requestDateTime' => $this->valueFormatter->formatDateTime($order['transaction_time'], 'requestDateTime'),
        ];

        if (null !== $posAccount->getSubMerchantId()) {
            $inputs['subMerchantId'] = $posAccount->getSubMerchantId();
        }

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['creditCard']  = $creditCard->getNumber();
            $inputs['expiredDate'] = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'expiredDate');
            $inputs['cvv']         = $creditCard->getCvv();
        }

        $data = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];

        $event = new Before3DFormHashCalculatedEvent(
            $data['inputs'],
            $posAccount->getBankName(),
            $txType,
            $paymentModel,
            AkbankPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $data['inputs'] = $event->getFormInputs();

        $data['inputs']['hash'] = $this->crypt->create3DHash($posAccount, $data['inputs']);

        return $data;
    }

    /**
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    private function applyPaymentDefaults(array $order): array
    {
        if (isset($order['recurring'])) {
            $order['installment'] = 0;
        }

        return \array_merge($order, [
            'id'               => $order['id'],
            'amount'           => $order['amount'],
            'ip'               => $order['ip'],
            'installment'      => $order['installment'] ?? 0,
            'currency'         => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'transaction_time' => $this->createDateTime(),
        ]);
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * @return array{terminal: array{merchantSafeId: string, terminalSafeId: string}}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        $data = [
            'terminal' => [
                'merchantSafeId' => $posAccount->getMerchantId(),
                'terminalSafeId' => $posAccount->getTerminalId(),
            ],
        ];

        if (null !== $posAccount->getSubMerchantId()) {
            $data['subMerchant'] = [
                'subMerchantId' => $posAccount->getSubMerchantId(),
            ];
        }

        return $data;
    }

    /**
     * @param array{frequency: int<1, 99>, frequencyType: string, installment: int<2, 120>} $recurringData
     *
     * @return array{recurring: array{frequencyInterval: int<1, 99>, frequencyCycle: string, numberOfPayments: int<2,
     *                          120>}}
     */
    private function createRecurringData(array $recurringData): array
    {
        return [
            'recurring' => [
                // Periyodik İşlem Frekansı
                'frequencyInterval' => $recurringData['frequency'],
                // D|W|M|Y
                'frequencyCycle'    => $this->valueMapper->mapRecurringFrequency($recurringData['frequencyType']),
                'numberOfPayments'  => $recurringData['installment'],
            ],
        ];
    }

    /**
     * @return \DateTimeImmutable
     */
    private function createDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Istanbul'));
    }
}
