<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\PosNetPos;

/**
 * @internal
 */
class PosNetPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @var string */
    protected const HASH_SEPARATOR = ';';

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs, ?string $txType = null): string
    {
        throw new NotImplementedException();
    }

    /**
     * @param PosNetPosAccount $posAccount
     *
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (null === $posAccount->getSecretKey()) {
            throw new \LogicException('Account secretKey eksik!');
        }

        $secondHashData = [
            $data['mdStatus'],
            $data['xid'],
            $data['amount'],
            $data['currency'],
            $posAccount->getMerchantId(),
            $this->createSecurityData($posAccount->getSecretKey(), $posAccount->getTerminalId()),
        ];
        $hashStr        = implode(static::HASH_SEPARATOR, $secondHashData);

        if ($this->hashString($hashStr) !== $data['mac']) {
            $this->logger->error('hash check failed', [
                'order_id' => $data['xid'],
            ]);

            return false;
        }

        $this->logger->debug('hash check is successful', [
            'order_id' => $data['xid'],
        ]);

        return true;
    }

    /**
     * @param array{amount: int, currency: string, id: string} $order
     *
     * @inheritdoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData, array $order = []): string
    {
        if (null === $posAccount->getSecretKey()) {
            throw new \LogicException('Account secretKey eksik!');
        }

        $hashData = [
            $order['id'],
            $order['amount'],
            $order['currency'],
            $requestData['mid'],
            $this->createSecurityData($posAccount->getSecretKey(), $requestData['tid']),
        ];
        $hashStr  = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * Make Security Data
     *
     * @param string $secretKey
     * @param string $terminalId
     *
     * @return string
     */
    private function createSecurityData(string $secretKey, string $terminalId): string
    {
        $hashData = [
            $secretKey,
            $terminalId,
        ];
        $hashStr  = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
