<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Serializer\Decoder\DecoderInterface;
use Mews\Pos\Serializer\Encoder\EncoderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
abstract class AbstractIyzicoPosHttpClient extends AbstractHttpClient
{
    protected IyzicoPosCrypt $crypt;

    public function __construct(
        string                  $baseApiUrl,
        ClientInterface         $psrClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        LoggerInterface         $logger,
        CryptInterface          $crypt,
        EncoderInterface        $encoder,
        DecoderInterface        $decoder
    ) {
        if (!$crypt instanceof IyzicoPosCrypt) {
            throw new \LogicException(\sprintf(
                'Expected %s, got %s.',
                IyzicoPosCrypt::class,
                $crypt::class
            ));
        }

        parent::__construct(
            $baseApiUrl,
            $psrClient,
            $requestFactory,
            $streamFactory,
            $encoder,
            $decoder,
            $logger
        );

        $this->crypt = $crypt;
    }

    protected function createAuthorizationHeader(string $url, string $requestBody, IyzicoPosAccount $account): string
    {
        $randomKey = $this->crypt->generateRandomString();
        $data      = [
            'rnd'         => $randomKey,
            'uri'         => (string) \parse_url($url, \PHP_URL_PATH),
            'requestBody' => $requestBody,
        ];
        $signature = $this->crypt->createHash($account, $data);

        $authStr = \sprintf('apiKey:%s&randomKey:%s&signature:%s', $account->getApiKey(), $randomKey, $signature);

        return 'IYZWSv2 '.\base64_encode($authStr);
    }

    /**
     * @inheritDoc
     */
    protected function checkFailResponseData(string $txType, ResponseInterface $response, array $responseData, array $order): void
    {
        if ($response->getStatusCode() >= 400) {
            $this->logger->error('Api request failed!', [
                'status_code'   => $response->getStatusCode(),
                'response_data' => $responseData,
                'response'      => $response->getBody()->getContents(),
                'tx_type'       => $txType,
                'order'         => $order,
            ]);
            throw new \RuntimeException($responseData['errorMessage'], $response->getStatusCode());
        }
    }
}
