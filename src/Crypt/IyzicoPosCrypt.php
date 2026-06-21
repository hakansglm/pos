<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\IyzicoPos;

class IyzicoPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return IyzicoPos::class === $gatewayClass;
    }

    /**
     * Verifies the callback signature sent by iyzico after 3D authentication.
     *
     * @inheritDoc
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (!$posAccount instanceof IyzicoPosAccount) {
            throw new \LogicException('IyzicoPosAccount is required for hash check.');
        }

        $dataToSign = \implode(':', [
            $data['conversationData'] ?? '',
            $data['conversationId'] ?? '',
            $data['mdStatus'] ?? '',
            $data['paymentId'] ?? '',
            $data['status'] ?? '',
        ]);

        $expected = $this->hashString($dataToSign, $posAccount->getStoreKey());

        if (isset($data['signature']) && \hash_equals($expected, $data['signature'])) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $expected,
            'expected_hash'  => $data['signature'] ?? null,
        ]);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        $hashData = [
            $requestData['rnd'],
            $requestData['uri'],
            $requestData['requestBody'],
        ];
        $payload = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($payload, $posAccount->getStoreKey());
    }

    /**
     * Returns bin2hex HMAC-SHA256 of the given string.
     *
     * @inheritDoc
     */
    public function hashString(string $str, ?string $encryptionKey = null): string
    {
        if (null === $encryptionKey) {
            throw new \LogicException('Encryption key is required.');
        }

        return \bin2hex(\hash_hmac(static::HASH_ALGORITHM, $str, $encryptionKey, true));
    }
}
