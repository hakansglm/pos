<?php

/**
 * @license
 */

namespace Mews\Pos\Model\Account;

class ParamPosAccount extends AbstractPosAccount
{
    /**
     * @param string      $bankName
     * @param string      $merchantId CLIENT_CODE
     * @param string      $username   CLIENT_USERNAME Kullanıcı adı
     * @param string      $password   CLIENT_PASSWORD Şifre
     * @param string      $secretKey  GUID  Üye İşyeri ait anahtarı
     * @param string|null $terminalId Terminal_ID
     */
    public function __construct(
        string  $bankName,
        string  $merchantId,
        string  $username,
        string  $password,
        string  $secretKey,
        ?string $terminalId = null
    ) {
        parent::__construct($bankName, $merchantId, $username, $password, $secretKey, $terminalId);
    }
}
