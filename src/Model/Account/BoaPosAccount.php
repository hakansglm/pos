<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Model\Account;

/**
 * Used for KuveytPos and VakifKatilimPos gateways
 */
class BoaPosAccount extends AbstractPosAccount
{
    /**
     * @param string      $bankName
     * @param string      $merchantId    Mağaza Numarası
     * @param string      $username      POS panelinizden kullanıcı işlemleri sayfasında APİ rolünde kullanıcı oluşturulmalıdır
     * @param string      $customerId    CustomerNumber, Müşteri No
     * @param string      $secretKey     Oluşturulan APİ kullanıcısının şifre bilgisidir.
     * @param string|null $subMerchantId
     */
    public function __construct(
        string  $bankName,
        string  $merchantId,
        string  $username,
        string  $customerId,
        string  $secretKey,
        ?string $subMerchantId = null
    ) {
        parent::__construct($bankName, $merchantId, $username, '', $secretKey, $customerId, $subMerchantId);
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->getTerminalId();
    }
}
