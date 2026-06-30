<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Request\Mapper;

use Mews\Pos\DataMapper\Request\ValueFormatter\ParamPosRequestValueFormatter;
use Mews\Pos\DataMapper\Request\ValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\ParamPosRequestValueMapper;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for Param3DHostPos Gateway requests
 *
 * @internal
 */
class Param3DHostPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * @var ParamPosRequestValueMapper
     */
    protected RequestValueMapperInterface $valueMapper;

    /**
     * @var ParamPosRequestValueFormatter
     */
    protected RequestValueFormatterInterface $valueFormatter;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return Param3DHostPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param ParamPosAccount                      $posAccount
     * @param array<string, int|string|float|null> $order
     * @param PosInterface::TX_TYPE_PAY_*          $txType
     *
     * @return array<string, mixed>
     */
    public function create3DFormInitializeRequestData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        /** @var array<string, mixed> $order */
        $order = \array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'      => $order['amount'],
            'ip'          => $order['ip'],
        ]);

        $requestData = [
            '@xmlns'           => 'https://turkodeme.com.tr/',
            // Bu alan editable olsun istiyorsanız başına “e|”,
            // readonly olsun istiyorsanız başına “r|” eklemelisiniz.
            'Borclu_Tutar'     => 'r|'.$this->valueFormatter->formatAmount($order['amount'], $txType),
            'Borclu_Odeme_Tip' => 'r|Diğer',
            'Borclu_AdSoyad'   => 'r|',
            'Borclu_Aciklama'  => 'r|',
            'Return_URL'       => 'r|'.$order['success_url'],
            'Islem_ID'         => $this->crypt->generateRandomString(),
            'Borclu_Kisi_TC'   => '',
            'Terminal_ID'      => $posAccount->getTerminalId(),
            'Borclu_GSM'       => 'r|',
            // = 0 ise tüm taksitler listelenir. > 0 ise sadece o taksit seçeneği listelenir.
            'Taksit'           => $this->valueFormatter->formatInstallment(max(0, (int) $order['installment'])),
        ];

        if (PosInterface::CURRENCY_TRY !== $order['currency']) {
            $requestData['Doviz_Kodu'] = $this->valueMapper->mapCurrency($order['currency']);
        }

        $soapAction = $this->valueMapper->mapTxType($txType, PosInterface::MODEL_3D_HOST);

        return $this->wrapSoapEnvelope([$soapAction => $requestData], $posAccount);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string|array<string, string>>
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }


    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }


    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $extraData
     */
    public function create3DFormData(
        ?AbstractPosAccount   $posAccount,
        ?array                $order,
        string                $paymentModel,
        string                $txType,
        ?string               $gatewayURL = null,
        ?CreditCardInterface  $creditCard = null,
        ?array                $extraData = null
    ): array {
        if (null === $extraData) {
            throw new \InvalidArgumentException('$extraData can not be null');
        }

        if (PosInterface::MODEL_3D_HOST !== $paymentModel) {
            throw new \InvalidArgumentException();
        }

        if (null === $gatewayURL) {
            throw new \InvalidArgumentException('Please provide $gatewayURL');
        }

        $decoded = \base64_decode($extraData['TO_Pre_Encrypting_OOSResponse']['TO_Pre_Encrypting_OOSResult'], true);
        if (false === $decoded) {
            throw new \RuntimeException($extraData['TO_Pre_Encrypting_OOSResponse']['TO_Pre_Encrypting_OOSResult']);
        }

        $inputs = [
            's' => (string) $extraData['TO_Pre_Encrypting_OOSResponse']['TO_Pre_Encrypting_OOSResult'],
        ];

        return [
            'gateway' => $gatewayURL,
            'method'  => 'GET',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param array<string, mixed> $data
     * @param AbstractPosAccount   $posAccount
     *
     * @return array{"soap:Body": array<string, mixed>, "soap:Header"?: array<string, mixed>}
     */
    private function wrapSoapEnvelope(array $data, AbstractPosAccount $posAccount): array
    {
        return [
            'soap:Header' => [
                'ServiceSecuritySoapHeader' => [
                    '@xmlns'          => 'https://turkodeme.com.tr/',
                    'CLIENT_CODE'     => $posAccount->getMerchantId(),
                    'CLIENT_USERNAME' => $posAccount->getUsername(),
                    'CLIENT_PASSWORD' => $posAccount->getPassword(),
                ],
            ],
            'soap:Body'   => $data,
        ];
    }
}
