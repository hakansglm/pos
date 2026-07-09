<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * Maps order/request values to values that are expected by the POS API.
 *
 * @internal
 */
interface ResponseValueMapperInterface
{
    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @param string|int $txType
     *
     * @return PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_*|null
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string;

    /**
     * @param string|bool|int                                         $securityType
     * @param PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_* $apiRequestTxType the transaction type of the API request.
     *
     * @return PosInterface::MODEL_*|null
     */
    public function mapSecureType($securityType, string $apiRequestTxType): ?string;

    /**
     * @param string|int                                              $currency
     * @param PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_* $apiRequestTxType the transaction type of the API request.
     *
     * @return PosInterface::CURRENCY_*|null
     */
    public function mapCurrency($currency, string $apiRequestTxType): ?string;

    /**
     * maps order status of status and history requests.
     * If the order status is not mapped, it should return the original value.
     *
     * @param string|int $orderStatus
     *
     * @return PosInterface::PAYMENT_STATUS_*|string|int
     */
    public function mapOrderStatus($orderStatus);

    /**
     * Maps a bank-specific card type string to a unified CreditCardInterface::CARD_TYPE_* constant.
     * Returns null when the value is unknown or the bank does not return card type in responses.
     *
     * @return CreditCardInterface::CARD_TYPE_*|null
     */
    public function mapCardType(?string $cardType): ?string;

    /**
     * Normalizes a raw card family name from the bank into a canonical form.
     * Returns a CreditCardInterface::CARD_FAMILY_* constant when the name is recognised,
     * the input unchanged when no mapping is defined for it, and null when the input is null.
     *
     * @return CreditCardInterface::CARD_FAMILY_*|string|null
     */
    public function mapCardFamilyName(?string $name): ?string;

    /**
     * Maps a bank-specific card class string (credit/debit/prepaid) to a CreditCardInterface::CARD_CLASS_* constant.
     * Returns null when the value is unknown or the bank does not return card class in responses.
     *
     * @return CreditCardInterface::CARD_CLASS_*|null
     */
    public function mapCardClass(?string $cardClass): ?string;
}
