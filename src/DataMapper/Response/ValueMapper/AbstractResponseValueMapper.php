<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\ValueMapper;

use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
abstract class AbstractResponseValueMapper implements ResponseValueMapperInterface
{
    /** @var array<string|int, PosInterface::CURRENCY_*> */
    protected array $currencyMappings = [];

    /** @var array<PosInterface::TX_TYPE_*|PosQueryInterface::QUERY_TYPE_*, string|array<PosInterface::MODEL_*, string>> */
    protected array $txTypeMappings = [];

    /** @var array<string|int, PosInterface::MODEL_*> */
    protected array $secureTypeMappings = [];

    /**
     * @var array<string|int, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [];

    /**
     * @inheritDoc
     */
    public function mapTxType($txType, ?string $paymentModel = null): ?string
    {
        if ([] === $this->txTypeMappings) {
            throw new \LogicException('Transaction type mapping is not supported');
        }

        foreach ($this->txTypeMappings as $mappedTxType => $mapping) {
            if (\is_array($mapping) && \in_array($txType, $mapping, true)) {
                return $mappedTxType;
            }

            if ($mapping === $txType) {
                return $mappedTxType;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function mapSecureType($securityType, ?string $apiRequestTxType = null): ?string
    {
        if ([] === $this->secureTypeMappings) {
            throw new \LogicException('Secure type mapping is not supported');
        }

        return $this->secureTypeMappings[$securityType] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function mapCurrency($currency, ?string $apiRequestTxType = null): ?string
    {
        return $this->currencyMappings[$currency] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function mapOrderStatus($orderStatus)
    {
        if ([] === $this->orderStatusMappings) {
            throw new \LogicException('Order status mapping is not supported.');
        }

        return $this->orderStatusMappings[$orderStatus] ?? $orderStatus;
    }

    /**
     * Default implementation: returns null.
     * Override in bank-specific subclasses that return card type in their responses.
     *
     * @inheritDoc
     */
    public function mapCardType(?string $cardType): ?string
    {
        return null;
    }

    /**
     * Default implementation: pass-through.
     * Override in bank-specific subclasses that return non-canonical card family names.
     *
     * @inheritDoc
     */
    public function mapCardFamilyName(?string $name): ?string
    {
        return $name;
    }

    /**
     * Default implementation: returns null.
     * Override in bank-specific subclasses that return card class in their responses.
     *
     * @inheritDoc
     */
    public function mapCardClass(?string $cardClass): ?string
    {
        return null;
    }
}
