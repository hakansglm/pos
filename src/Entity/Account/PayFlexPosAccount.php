<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

/**
 * used by PayFlexPos and PayFlexCPV4Pos gateways
 */
class PayFlexPosAccount extends AbstractPosAccount
{
    /** @var int */
    public const MERCHANT_TYPE_STANDARD = 0;

    /** @var int */
    public const MERCHANT_TYPE_MAIN_DEALER = 1;

    /** @var int */
    public const MERCHANT_TYPE_SUB_DEALER = 2;

    /** @var int[] */
    private static array $merchantTypes = [
        self::MERCHANT_TYPE_STANDARD,
        self::MERCHANT_TYPE_MAIN_DEALER,
        self::MERCHANT_TYPE_SUB_DEALER,
    ];

    /**
     * @param string                $bankName
     * @param string                $merchantId    Isyeri No
     * @param string                $password      Isyeri Sifre
     * @param string                $terminalId    Terminal No
     * @param self::MERCHANT_TYPE_* $merchantType
     * @param string|null           $subMerchantId
     */
    public function __construct(
        string         $bankName,
        string         $merchantId,
        string         $password,
        private string $terminalId,
        /**
         * Banka tarafından Üye işyerine iletilmektedir
         */
        private int    $merchantType = self::MERCHANT_TYPE_STANDARD,
        ?string        $subMerchantId = null
    ) {
        parent::__construct($bankName, $merchantId, '', $password, 'tr', $subMerchantId);
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    /**
     * @return int
     */
    public function getMerchantType(): int
    {
        return $this->merchantType;
    }

    /**
     * @return bool
     */
    public function isSubBranch(): bool
    {
        return self::MERCHANT_TYPE_SUB_DEALER === $this->merchantType;
    }

    /**
     * @return int[]
     */
    public static function getMerchantTypes(): array
    {
        return self::$merchantTypes;
    }
}
