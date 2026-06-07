<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\IyzicoPosCrypt;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractIyzicoPosHttpClient extends AbstractHttpClient
{
    protected IyzicoPosCrypt $crypt;
    protected RequestValueMapperInterface $requestValueMapper;

    public function __construct(
        string                      $baseApiUrl,
        ClientInterface             $psrClient,
        RequestFactoryInterface     $requestFactory,
        StreamFactoryInterface      $streamFactory,
        SerializerInterface         $serializer,
        LoggerInterface             $logger,
        CryptInterface              $crypt,
        RequestValueMapperInterface $requestValueMapper
    ) {
        if (!$crypt instanceof IyzicoPosCrypt) {
            throw new \LogicException(\sprintf('Expected %s, got %s.', IyzicoPosCrypt::class, \get_class($crypt)));
        }

        parent::__construct($baseApiUrl, $psrClient, $requestFactory, $streamFactory, $serializer, $logger);

        $this->crypt              = $crypt;
        $this->requestValueMapper = $requestValueMapper;
    }

    protected function createAuthorizationHeader(string $url, string $requestBody, AbstractPosAccount $account): string
    {
        $randomKey = $this->crypt->generateRandomString();
        $data      = [
            'rnd'         => $randomKey,
            'uri'         => (string) \parse_url($url, \PHP_URL_PATH),
            'requestBody' => $requestBody,
        ];
        $signature = $this->crypt->createHash($account, $data);

        $authStr = \sprintf('apiKey:%s&randomKey:%s&signature:%s', $account->getClientId(), $randomKey, $signature);

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
