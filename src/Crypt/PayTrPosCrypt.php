<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Gateway\PayTrPos;

class PayTrPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @inheritDoc */
    public static function supports(string $gatewayClass): bool
    {
        return PayTrPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * Verifies the hash sent by PayTR in the callback notification.
     * Formula: base64_encode(HMAC-SHA256(merchant_oid + merchant_salt + status + total_amount, merchant_key))
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        $merchantKey  = (string) $posAccount->getStoreKey();
        $merchantSalt = $posAccount->getPassword();

        $hashStr = ($data['merchant_oid'] ?? '')
            .$merchantSalt
            .($data['status'] ?? '')
            .($data['total_amount'] ?? '');

        $expected = $this->hashString($hashStr, $merchantKey);

        if (\hash_equals($expected, (string) ($data['hash'] ?? ''))) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $expected,
            'expected_hash'  => $data['hash'] ?? null,
        ]);

        return false;
    }

    /**
     * @inheritDoc
     *
     * Builds paytr_token for payment requests.
     * Detects the correct formula by inspecting which keys exist in $requestData:
     *  - iFrame token:    no_installment present
     *  - Direct payment:  non_3d present
     *  - Refund:          return_amount present
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        if (isset($requestData['start_date'], $requestData['end_date'])) {
            // History:
            $hashParamKeyValue = 'merchant_id:start_date:end_date';
        } elseif (isset($requestData['return_amount'])) {
            // Refund:
            $hashParamKeyValue = 'merchant_id:merchant_oid:return_amount';
        } elseif (isset($requestData['non_3d'])) {
            // Direct payment:
            $hashParamKeyValue = 'merchant_id:user_ip:merchant_oid:email:payment_amount:payment_type:installment_count:currency:test_mode:non_3d';
        } elseif (isset($requestData['no_installment']) || isset($requestData['max_installment'])) {
            // iFrame token:
            $hashParamKeyValue = 'merchant_id:user_ip:merchant_oid:email:payment_amount:user_basket:no_installment:max_installment:currency:test_mode';
        } else {
            // order status
            $hashParamKeyValue = 'merchant_id:merchant_oid';
        }

        return $this->hashFromParams(
            $posAccount,
            $requestData,
            $hashParamKeyValue,
            ':'
        );
    }

    /** @inheritDoc */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        return $this->createHash($posAccount, $formInputs);
    }

    /**
     * @inheritDoc
     */
    public function hashFromParams(AbstractPosAccount $account, array $data, string $hashParamsValue, string $paramSeparator = ':'): string
    {
        if ('' === $hashParamsValue) {
            throw new \InvalidArgumentException('hashParamsValue cannot be empty');
        }

        $storeKey = $account->getStoreKey();
        if (null === $storeKey) {
            throw new \LogicException('Account storeKey eksik!');
        }

        /** @var non-empty-string $hashParamsValue ex: "MerchantNo:TerminalNo:ReferenceCode:OrderId" */
        $hashParamsArr = \explode($paramSeparator, $hashParamsValue);

        $hashVal = $this->buildHashString($data, $hashParamsArr, '', $account->getPassword());

        return $this->hashString($hashVal, $account->getStoreKey());
    }

    /**
     * @inheritDoc
     */
    public function hashString(string $str, ?string $encryptionKey = null): string
    {
        return \base64_encode(\hash_hmac(self::HASH_ALGORITHM, $str, (string) $encryptionKey, true));
    }
}
