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
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractRequestDataMapper
 *
 * @internal
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
}
