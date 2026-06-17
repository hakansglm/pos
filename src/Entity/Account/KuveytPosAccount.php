<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

/**
 * KuveytPosAccount
 */
class KuveytPosAccount extends AbstractPosAccount
{
    /**
     * @param string      $bankName
     * @param string      $merchantId    Mağaza Numarası
     * @param string      $username      POS panelinizden kullanıcı işlemleri sayfasında APİ rolünde kullanıcı oluşturulmalıdır
     * @param string      $customerId    CustomerNumber, Müşteri No
     * @param string      $storeKey      Oluşturulan APİ kullanıcısının şifre bilgisidir.
     * @param string|null $subMerchantId
     */
    public function __construct(
        string  $bankName,
        string  $merchantId,
        string  $username,
        string  $customerId,
        string  $storeKey,
        ?string $subMerchantId = null
    ) {
        parent::__construct($bankName, $merchantId, $username, $customerId, $storeKey, $subMerchantId);
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->password;
    }
}
