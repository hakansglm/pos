<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Exception\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

interface HttpClientInterface
{
    /**
     * Main api.
     */
    public const API_NAME_PAYMENT_API = 'payment_api';

    /**
     * Generally used for status, history queries.
     * Only some gateways support this api.
     */
    public const API_NAME_QUERY_API = 'query_api';

    /**
     * If gateway generates 3D form data by making a request to Gateway API,
     * then this api should be used.
     */
    public const API_NAME_GATEWAY_3D_API = 'gateway_3d';

    /**
     * @param PosInterface::TX_TYPE_*      $txType
     * @param PosInterface::MODEL_*        $paymentModel
     * @param PosInterface::TX_TYPE_*|null $orderTxType
     *
     * @return bool
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool;

    /**
     * @param class-string<PosInterface> $gatewayClass
     * @param self::API_NAME_*           $apiName
     *
     * @return bool
     */
    public static function supports(string $gatewayClass, string $apiName): bool;

    /**
     * $orderTxType value is never an internal transaction type (TX_TYPE_INTERNAL_*).
     * On the other hand, $txType can be any Transaction Type (TX_TYPE_*).
     *
     * $orderTxType is mostly set when $txType value is an internal Transaction Type.
     *
     * @param PosInterface::TX_TYPE_*          $txType       Transaction type to be used to decide to which endpoint the request will be send to.
     * @param PosInterface::MODEL_*            $paymentModel
     * @param array<string, mixed>             $requestData
     * @param array<string, mixed>             $order
     * @param non-empty-string|null            $url
     * @param AbstractPosAccount|null          $account
     * @param PosInterface::TX_TYPE_PAY_*|null $orderTxType  In some cases $txType alone is not enough to determine API endpoint.
     *                                                       Transaction type of the order is used in this case.
     *
     * @return array<string, mixed>|string
     *
     * @throws UnsupportedTransactionTypeException
     * @throws NotEncodableValueException
     * @throws ClientExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function request(
        string $txType,
        string $paymentModel,
        array $requestData,
        array $order,
        ?string $url = null,
        ?AbstractPosAccount $account = null,
        ?string $orderTxType = null
    );
}
