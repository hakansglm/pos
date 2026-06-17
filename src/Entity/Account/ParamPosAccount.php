<?php

/**
 * @license
 */

namespace Mews\Pos\Entity\Account;

class ParamPosAccount extends AbstractPosAccount
{
    /**
     * @param string $bankName
     * @param int    $clientId  CLIENT_CODE Terminal ID
     * @param string $username  CLIENT_USERNAME Kullanıcı adı
     * @param string $password  CLIENT_PASSWORD Şifre
     * @param string $secretKey GUID  Üye İşyeri ait anahtarı
     */
    public function __construct(
        string $bankName,
        int    $clientId,
        string $username,
        string $password,
        string $secretKey
    ) {
        parent::__construct($bankName, (string) $clientId, $username, $password, $secretKey);
    }
}
