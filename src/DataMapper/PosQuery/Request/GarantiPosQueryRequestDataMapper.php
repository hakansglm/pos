<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\DataMapper\Request\ValueFormatter\GarantiPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class GarantiPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    private const API_VERSION = '512';

    private const MOTO = 'N';

    /** @var GarantiPosRequestValueFormatter */
    protected RequestValueFormatterInterface $valueFormatter;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @param \Mews\Pos\Model\Account\GarantiPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += [
            'Mode'     => $this->getMode(),
            'Version'  => self::API_VERSION,
            'Terminal' => $this->getTerminalData($posAccount),
        ];

        if (!isset($requestData['Terminal']['HashData']) || '' === $requestData['Terminal']['HashData']) {
            $requestData['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
    }

    /**
     * @param \Mews\Pos\Model\Account\GarantiPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        $txType = PosQueryInterface::QUERY_TYPE_HISTORY;

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $data['ip'],
            ],
            'Order'       => [
                'OrderID'     => null,
                'GroupID'     => null,
                'Description' => null,
                'StartDate'   => $this->valueFormatter->formatDateTime($data['start_date'], 'StartDate', $txType),
                'EndDate'     => $this->valueFormatter->formatDateTime($data['end_date'], 'EndDate', $txType),
                'ListPageNum' => $data['page'] ?? 1,
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType($txType),
                'Amount'                => $this->valueFormatter->formatAmount(1),
                'CurrencyCode'          => $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param \Mews\Pos\Model\Account\GarantiPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createBinListRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $txType = PosQueryInterface::QUERY_TYPE_BIN_LIST;

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => 'v0.1',
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => ['IPAddress' => $params['ip']],
            'Order'       => ['OrderID' => $this->crypt->generateRandomString()],
            'Transaction' => [
                'Type'   => $this->valueMapper->mapTxType($txType),
                'Amount' => $this->valueFormatter->formatAmount(1),
                'BINInq' => [
                    // Group; A – all (hepsi ) G - garanti B – Bonusnet
                    'Group'    => 'A',
                    // CardType; A – all (hepsi ) C - credit (kredi kartı) D-Debit kart
                    'CardType' => $this->valueMapper->mapCardClass($params['card_class'] ?? null),
                ],
            ],
        ];

        if (isset($params['bin'])) {
            $result['Transaction']['BINInq']['BINNum'] = (string) $params['bin'];
        }

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    private function getMode(): string
    {
        return $this->isTestMode() ? 'TEST' : 'PROD';
    }

    /**
     * @param \Mews\Pos\Model\Account\GarantiPosAccount $posAccount
     *
     * @return array{ProvUserID: string, UserID: string, HashData: string, ID: string, MerchantID: string}
     */
    private function getTerminalData(AbstractPosAccount $posAccount): array
    {
        return [
            'ProvUserID' => $posAccount->getUsername(),
            'UserID'     => $posAccount->getUsername(),
            'HashData'   => '',
            'ID'         => $posAccount->getTerminalId(),
            'MerchantID' => $posAccount->getMerchantId(),
        ];
    }
}
