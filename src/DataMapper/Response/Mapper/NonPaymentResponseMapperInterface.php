<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\Response\Mapper;

interface NonPaymentResponseMapperInterface
{
    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapRefundResponse(array $rawResponseData): array;

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapCancelResponse(array $rawResponseData): array;

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapStatusResponse(array $rawResponseData): array;

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapHistoryResponse(array $rawResponseData): array;

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array;
}
