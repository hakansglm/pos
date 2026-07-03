<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\PosQuery\Request;

use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\PosQuery\PosQueryInterface;

/**
 * @internal
 */
class ParamPosQueryRequestDataMapper extends AbstractQueryRequestDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        /** @var string $soapAction */
        $soapAction               = \array_key_first($requestData);
        $requestData[$soapAction] += $this->getRequestAccountData($posAccount);

        return $this->wrapSoapEnvelope($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data): array
    {
        $requestData = $this->getRequestAccountData($posAccount) + [
            '@xmlns'    => 'https://turkpos.com.tr/',
            'Tarih_Bas' => $this->valueFormatter->formatDateTime($data['start_date'], 'Tarih_Bas'),
            'Tarih_Bit' => $this->valueFormatter->formatDateTime($data['end_date'], 'Tarih_Bit'),
        ];

        if (isset($data['order_status'])) {
            $requestData['Islem_Durum'] = $data['order_status'];
        }

        if (isset($data['transaction_type'])) {
            if (PosInterface::TX_TYPE_PAY_AUTH === $data['transaction_type']) {
                $requestData['Islem_Tip'] = 'Satış';
            } elseif (PosInterface::TX_TYPE_CANCEL === $data['transaction_type']) {
                $requestData['Islem_Tip'] = 'İptal';
            } elseif (PosInterface::TX_TYPE_REFUND === $data['transaction_type']) {
                $requestData['Islem_Tip'] = 'İade';
            }
        }

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosQueryInterface::QUERY_TYPE_HISTORY) => $requestData,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function createBinListRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = ['@xmlns' => 'https://turkpos.com.tr/'] + $this->getRequestAccountData($posAccount);
        if (isset($params['bin'])) {
            $requestData['BIN'] = (string) $params['bin'];
        }

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosQueryInterface::QUERY_TYPE_BIN_LIST) => $requestData,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function createInstallmentRatesRequestData(AbstractPosAccount $posAccount, array $params): array
    {
        $requestData = ['@xmlns' => 'https://turkpos.com.tr/'] + $this->getRequestAccountData($posAccount);

        return $this->wrapSoapEnvelope([
            $this->valueMapper->mapTxType(PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES) => $requestData,
        ]);
    }

    /**
     * @return array{G: array{CLIENT_CODE: string, CLIENT_USERNAME: string, CLIENT_PASSWORD: string}, GUID: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'G'    => [
                'CLIENT_CODE'     => $posAccount->getMerchantId(),
                'CLIENT_USERNAME' => $posAccount->getUsername(),
                'CLIENT_PASSWORD' => $posAccount->getPassword(),
            ],
            'GUID' => $posAccount->getSecretKey(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{"soap:Body": array<string, mixed>}
     */
    private function wrapSoapEnvelope(array $data): array
    {
        return [
            'soap:Body' => $data,
        ];
    }
}
