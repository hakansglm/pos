<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;

interface CryptInterface
{
    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @param string      $str
     * @param string|null $encryptionKey
     *
     * @return non-empty-string
     */
    public function hashString(string $str, ?string $encryptionKey = null): string;

    /**
     * check hash of 3D secure response
     *
     * @param AbstractPosAccount    $posAccount
     * @param array<string, string> $data
     *
     * @return bool
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool;

    /**
     * creates hash for 3D form data
     *
     * @param AbstractPosAccount    $posAccount
     * @param array<string, string> $formInputs
     *
     * @return string
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string;

    /**
     * create hash for API requests
     *
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $requestData
     *
     * @return string
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string;

    /**
     * @param AbstractPosAccount   $account         account whose secretKey is the hashing key
     * @param array<string, mixed> $data            array that contains values for the params listed in $hashParamsValue
     * @param string               $hashParamsValue parameter names separated by $paramSeparator (e.g. "MerchantNo:TerminalNo")
     * @param non-empty-string     $paramSeparator  [:;+,]
     *
     * @return non-empty-string hashed string from values of $hashParamsValue
     *
     * @throws \InvalidArgumentException when $hashParamsValue is empty
     * @throws \LogicException           when account secretKey is null
     */
    public function hashFromParams(AbstractPosAccount $account, array $data, string $hashParamsValue, string $paramSeparator): string;


    /**
     * generates a random string for using as nonce in requests
     *
     * @param int<1, max> $length
     *
     * @return string
     */
    public function generateRandomString(int $length = 24): string;
}
