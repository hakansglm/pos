<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Event;

use Mews\Pos\PosInterface;

/**
 * This event is generated before the hash of 3D form data is calculated.
 * By listening to this event you can update 3D form input data before the hash is calculated,
 * if changes in input data are used while calculating the hash.
 */
class Before3DFormHashCalculatedEvent
{
    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     * @phpstan-param PosInterface::MODEL_3D_*    $paymentModel
     * @phpstan-param class-string<PosInterface>  $gatewayClass
     *
     * @param array<string, string> $formInputs
     * @param string                $bankName
     * @param string                $txType
     * @param string                $paymentModel
     * @param string                $gatewayClass
     */
    public function __construct(
        private array  $formInputs,
        private string $bankName,
        private string $txType,
        private string $paymentModel,
        private string $gatewayClass
    ) {
    }

    /**
     * @return PosInterface::MODEL_3D_*
     */
    public function getPaymentModel(): string
    {
        return $this->paymentModel;
    }

    /**
     * @return PosInterface::TX_TYPE_PAY_*
     */
    public function getTxType(): string
    {
        return $this->txType;
    }

    /**
     * @return string
     */
    public function getBankName(): string
    {
        return $this->bankName;
    }

    /**
     * @return array<string, string>
     */
    public function getFormInputs(): array
    {
        return $this->formInputs;
    }

    /**
     * @param array<string, string> $formInputs
     *
     * @return Before3DFormHashCalculatedEvent
     */
    public function setFormInputs(array $formInputs): self
    {
        $this->formInputs = $formInputs;

        return $this;
    }

    /**
     * @return class-string<PosInterface>
     */
    public function getGatewayClass(): string
    {
        return $this->gatewayClass;
    }
}
