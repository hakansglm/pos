<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\VakifKatilimPos;

/**
 * @internal
 */
class KuveytPosCrypt extends AbstractCrypt
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass
            || VakifKatilimPos::class === $gatewayClass
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        if (null === $posAccount->getSecretKey()) {
            throw new \LogicException('Account secretKey eksik!');
        }

        $hashedPassword = $this->hashString($posAccount->getSecretKey());

        $hashData = [
            $requestData['MerchantId'],
            // non-payment request may not have MerchantOrderId and Amount fields
            $requestData['MerchantOrderId'] ?? '',
            $requestData['Amount'] ?? '',

            // non 3d payments does not have OkUrl and FailUrl fields
            $requestData['OkUrl'] ?? '',
            $requestData['FailUrl'] ?? '',

            $requestData['UserName'],
            $hashedPassword,
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
