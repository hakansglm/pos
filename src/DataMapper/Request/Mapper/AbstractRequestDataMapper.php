<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractRequestDataMapper
 */
abstract class AbstractRequestDataMapper implements RequestDataMapperInterface
{
    protected bool $testMode = false;

    /**
     * @param RequestValueMapperInterface    $valueMapper
     * @param RequestValueFormatterInterface $valueFormatter
     * @param EventDispatcherInterface       $eventDispatcher
     * @param CryptInterface                 $crypt
     * @param PosInterface::LANG_*           $defaultLang
     */
    public function __construct(
        protected RequestValueMapperInterface    $valueMapper,
        protected RequestValueFormatterInterface $valueFormatter,
        protected EventDispatcherInterface       $eventDispatcher,
        protected CryptInterface                 $crypt,
        protected string                         $defaultLang = PosInterface::LANG_TR
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCrypt(): CryptInterface
    {
        return $this->crypt;
    }

    /**
     * @inheritDoc
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * @inheritDoc
     */
    public function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
    }

    /**
     * @inheritDoc
     */
    public function create3DFormInitializeRequestData(
        AbstractPosAccount   $posAccount,
        array                $order,
        string               $paymentModel,
        string               $txType,
        ?CreditCardInterface $creditCard = null
    ): array {
        throw new NotImplementedException('Not supported');
    }

    /**
     * according to the language value, the POS UI will be displayed in the selected language
     * and error messages will be returned in the selected language
     *
     * @param array<string, mixed> $order
     *
     * @return string if language mapping is not available, it returns default LANG_TR or as is.
     */
    protected function getLang(array $order): string
    {
        $lang = $order['lang'] ?? $this->defaultLang;

        return $this->valueMapper->mapLang($lang);
    }

    /**
     * prepares order for payment request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function preparePaymentOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for TX_TYPE_PAY_POST_AUTH type request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for order status request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareStatusOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for cancel request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareCancelOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for refund request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareRefundOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares history request
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return $data;
    }

    /**
     * prepares order for order history request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return $order;
    }
}
