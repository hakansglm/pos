<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Model\Account\AkbankPosAccount;
use Mews\Pos\Model\Account\AssecoPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Model\Account\GarantiPosAccount;
use Mews\Pos\Model\Account\InterPosAccount;
use Mews\Pos\Model\Account\BoaPosAccount;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\Model\Account\PayFlexPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Model\Account\ToslaPosAccount;
use Mews\Pos\Exception\MissingAccountInfoException;
use Mews\Pos\PosInterface;

/**
 * AccountFactory
 */
class AccountFactory
{
    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $clientId     Üye iş yeri (Mağaza) numarası
     * @param non-empty-string      $kullaniciAdi
     * @param non-empty-string      $password
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     *
     * @return AssecoPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createAssecoPosAccount(string $bank, string $clientId, string $kullaniciAdi, string $password, string $model = PosInterface::MODEL_NON_SECURE, ?string $storeKey = null): AssecoPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new AssecoPosAccount($bank, $clientId, $kullaniciAdi, $password, $storeKey);
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantSafeId 32 karakter üye İş Yeri numarası
     * @param non-empty-string      $terminalSafeId 32 karakter
     * @param non-empty-string      $secretKey
     * @param non-empty-string|null $subMerchantId  Max 15 karakter
     *
     * @return AkbankPosAccount
     */
    public static function createAkbankPosAccount(string $bank, string $merchantSafeId, string $terminalSafeId, string $secretKey, ?string $subMerchantId = null): AkbankPosAccount
    {
        return new AkbankPosAccount($bank, $merchantSafeId, $terminalSafeId, $secretKey, $subMerchantId);
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $apiKey
     * @param non-empty-string      $secretKey
     * @param non-empty-string|null $subMerchantKey
     *
     * @return IyzicoPosAccount
     */
    public static function createIyzicoPosAccount(string $bank, string $apiKey, string $secretKey, ?string $subMerchantKey = null): IyzicoPosAccount
    {
        return new IyzicoPosAccount($bank, $apiKey, $secretKey, $subMerchantKey);
    }

    /**
     * @param non-empty-string $bank
     * @param non-empty-string $clientId
     * @param non-empty-string $apiUser
     * @param non-empty-string $apiPass
     *
     * @return ToslaPosAccount
     */
    public static function createToslaPosAccount(string $bank, string $clientId, string $apiUser, string $apiPass): ToslaPosAccount
    {
        return new ToslaPosAccount($bank, $clientId, $apiUser, '', $apiPass);
    }

    /**
     * @phpstan-param PosInterface::MODEL_*      $model
     * @phpstan-param PayForPosAccount::MBR_ID_* $mbrId
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId   Üye işyeri numarası.
     * @param non-empty-string      $userCode     Otorizasyon sistemi kullanıcı kodu.
     * @param non-empty-string      $userPassword Otorizasyon sistemi kullanıcı şifresi.
     * @param non-empty-string      $model
     * @param non-empty-string|null $merchantPass 3D Secure şifresidir.
     * @param non-empty-string      $mbrId        Kurum kodudur.
     *
     * @return PayForPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayForPosAccount(
        string  $bank,
        string  $merchantId,
        string  $userCode,
        string  $userPassword,
        string  $model = PosInterface::MODEL_NON_SECURE,
        ?string $merchantPass = null,
        string  $mbrId = PayForPosAccount::MBR_ID_FINANSBANK
    ): PayForPosAccount {
        self::checkParameters($model, $merchantPass);

        return new PayForPosAccount(
            $bank,
            $merchantId,
            $userCode,
            $userPassword,
            $merchantPass,
            $mbrId
        );
    }

    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId     Üye işyeri Numarası
     * @param non-empty-string      $userId
     * @param non-empty-string      $password       Terminal UserID şifresi
     * @param non-empty-string      $terminalId
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     * @param non-empty-string|null $refundUsername
     * @param non-empty-string|null $refundPassword
     *
     * @return GarantiPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createGarantiPosAccount(string $bank, string $merchantId, string $userId, string $password, string $terminalId, string $model = PosInterface::MODEL_NON_SECURE, ?string $storeKey = null, ?string $refundUsername = null, ?string $refundPassword = null): GarantiPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new GarantiPosAccount($bank, $merchantId, $userId, $password, $terminalId, $storeKey, $refundUsername, $refundPassword);
    }


    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId    Mağaza Numarası / Üye iş yeri tekil numarası
     * @param non-empty-string      $username      Yönetim panelinden oluşturulan api rollü kullanıcı adı
     * @param non-empty-string      $customerId    CustomerNumber, Müşteri No
     * @param non-empty-string      $storeKey      Oluşturulan APİ kullanıcısının şifre bilgisidir.
     * @param non-empty-string      $model
     * @param non-empty-string|null $subMerchantId
     *
     * @return BoaPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createBoaPosAccount(string $bank, string $merchantId, string $username, string $customerId, string $storeKey, string $model = PosInterface::MODEL_3D_SECURE, ?string $subMerchantId = null): BoaPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new BoaPosAccount($bank, $merchantId, $username, $customerId, $storeKey, $subMerchantId);
    }

    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId
     * @param non-empty-string      $terminalId
     * @param non-empty-string      $posNetId
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     *
     * @return PosNetPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPosNetPosAccount(string $bank, string $merchantId, string $terminalId, string $posNetId, string $model = PosInterface::MODEL_NON_SECURE, ?string $storeKey = null): PosNetPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new PosNetPosAccount($bank, $merchantId, $posNetId, $terminalId, $storeKey);
    }

    /**
     * @phpstan-param PayFlexPosAccount::MERCHANT_TYPE_* $merchantType
     * @phpstan-param PosInterface::MODEL_*              $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId    Üye işyeri numarası
     * @param non-empty-string      $password      Üye işyeri şifres
     * @param non-empty-string      $terminalNo    İşlemin hangi terminal üzerinden gönderileceği bilgisi. dVB007000...
     * @param non-empty-string      $model
     * @param int                   $merchantType
     * @param non-empty-string|null $subMerchantId
     *
     * @return PayFlexPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayFlexPosAccount(string $bank, string $merchantId, string $password, string $terminalNo, string $model = PosInterface::MODEL_NON_SECURE, int $merchantType = PayFlexPosAccount::MERCHANT_TYPE_STANDARD, ?string $subMerchantId = null): PayFlexPosAccount
    {
        self::checkPayFlexBankMerchantType($merchantType, $subMerchantId);

        return new PayFlexPosAccount($bank, $merchantId, $password, $terminalNo, $merchantType, $subMerchantId);
    }

    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $shopCode
     * @param non-empty-string      $userCode
     * @param non-empty-string      $userPass
     * @param non-empty-string      $model
     * @param non-empty-string|null $merchantPass
     *
     * @return InterPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createInterPosAccount(string $bank, string $shopCode, string $userCode, string $userPass, string $model = PosInterface::MODEL_NON_SECURE, ?string $merchantPass = null): InterPosAccount
    {
        self::checkParameters($model, $merchantPass);

        return new InterPosAccount($bank, $shopCode, $userCode, $userPass, $merchantPass);
    }

    /**
     * @param string $bank
     * @param int    $clientCode CLIENT_CODE Terminal ID
     * @param string $username   CLIENT_USERNAME Kullanıcı adı
     * @param string $password   CLIENT_PASSWORD Şifre
     * @param string $guid       GUID  Üye İşyeri ait anahtarı
     *
     * @return ParamPosAccount
     */
    public static function createParamPosAccount(string $bank, int $clientCode, string $username, string $password, string $guid): ParamPosAccount
    {
        return new ParamPosAccount($bank, $clientCode, $username, $password, $guid);
    }

    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkParameters(string $model, ?string $storeKey): void
    {
        if (PosInterface::MODEL_NON_SECURE === $model) {
            return;
        }

        if (null !== $storeKey) {
            return;
        }

        throw new MissingAccountInfoException(\sprintf('payment model %s requires storeKey!', $model));
    }

    /**
     * @phpstan-param PayFlexPosAccount::MERCHANT_TYPE_* $merchantType
     *
     * @param int                   $merchantType
     * @param non-empty-string|null $subMerchantId
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkPayFlexBankMerchantType(int $merchantType, ?string $subMerchantId): void
    {
        if (PayFlexPosAccount::MERCHANT_TYPE_SUB_DEALER === $merchantType && null === $subMerchantId) {
            throw new MissingAccountInfoException('SubMerchantId is required for sub branches!');
        }

        if (!\in_array($merchantType, PayFlexPosAccount::getMerchantTypes())) {
            throw new MissingAccountInfoException('Invalid MerchantType!');
        }
    }
}
