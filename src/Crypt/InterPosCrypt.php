<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\InterPos;

/**
 * @internal
 */
class InterPosCrypt extends AbstractCrypt
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        $hashData = [
            $formInputs['ShopCode'],
            $formInputs['OrderId'],
            $formInputs['PurchAmount'],
            $formInputs['OkUrl'],
            $formInputs['FailUrl'],
            $formInputs['TxnType'],
            $formInputs['InstallmentCount'],
            $formInputs['Rnd'],
            $posAccount->getSecretKey(),
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        $actualHash = $this->hashFromParams($posAccount, $data, $data['HASHPARAMS'], ':');

        if (\hash_equals($data['HASH'], $actualHash)) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data' => $data,
            'generated_hash' => $actualHash,
            'expected_hash' => $data['HASH'],
        ]);

        return false;
    }

    /**
     * @inheritdoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        throw new NotImplementedException();
    }
}
