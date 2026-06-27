<?php

/**
 * @license
 */

namespace Mews\Pos\Model\Account;

class ParamPosAccount extends AbstractPosAccount
{
    /**
     * @param string $bankName
     * @param int    $merchantId CLIENT_CODE Terminal ID
     * @param string $username   CLIENT_USERNAME Kullanıcı adı
     * @param string $password   CLIENT_PASSWORD Şifre
     * @param string $secretKey  GUID  Üye İşyeri ait anahtarı
     */
    public function __construct(
        string $bankName,
        int    $merchantId,
        string $username,
        string $password,
        string $secretKey
    ) {
        parent::__construct($bankName, (string) $merchantId, $username, $password, $secretKey);
    }
}
