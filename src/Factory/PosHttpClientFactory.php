<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Client\AkbankPosHttpClient;
use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Client\IyzicoPos3DFormHttpClient;
use Mews\Pos\Client\IyzicoPosHttpClient;
use Mews\Pos\Client\IyzicoPosQueryApiHttpClient;
use Mews\Pos\Client\KuveytPosSoapApiHttpClient;
use Mews\Pos\Client\PosNetV1PosHttpClient;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PosHttpClientFactory
{
    /**
     * @template T of HttpClientInterface
     *
     * @param class-string<T>             $clientClass
     * @param non-empty-string            $baseApiUrl
     * @param SerializerInterface         $serializer
     * @param CryptInterface              $crypt
     * @param RequestValueMapperInterface $requestValueMapper
     * @param LoggerInterface             $logger
     * @param ClientInterface             $psr18client
     * @param RequestFactoryInterface     $requestFactory
     * @param StreamFactoryInterface      $streamFactory
     *
     * @return T
     */
    public static function create(
        string                      $clientClass,
        string                      $baseApiUrl,
        SerializerInterface         $serializer,
        CryptInterface              $crypt,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger,
        ClientInterface             $psr18client,
        RequestFactoryInterface     $requestFactory,
        StreamFactoryInterface      $streamFactory
    ): HttpClientInterface {
        if (AkbankPosHttpClient::class === $clientClass) {
            $client = new $clientClass(
                $baseApiUrl,
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger,
                $crypt
            );
        } elseif (IyzicoPosHttpClient::class === $clientClass
            || IyzicoPos3DFormHttpClient::class === $clientClass
            || IyzicoPosQueryApiHttpClient::class === $clientClass) {
            $client = new $clientClass(
                $baseApiUrl,
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger,
                $crypt,
                $requestValueMapper
            );
        } elseif (PosNetV1PosHttpClient::class === $clientClass || KuveytPosSoapApiHttpClient::class === $clientClass) {
            $client = new $clientClass(
                $baseApiUrl,
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger,
                $requestValueMapper
            );
        } else {
            $client = new $clientClass(
                $baseApiUrl,
                $psr18client,
                $requestFactory,
                $streamFactory,
                $serializer,
                $logger
            );
        }

        /** @var T $client */
        return $client;
    }
}
