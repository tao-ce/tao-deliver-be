<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Gateway;

use App\DynamicQueryApi\Exception\DynamicQueryApiException;
use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\Delivery;
use App\DynamicQueryApi\Model\SearchResponse;
use App\DynamicQueryApi\Serializer\Denormalizer\SearchResponseDenormalizer;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use ValueError;

class DynamicQueryApiGateway
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly HttpClientInterface $client,
        private readonly RequestStack $requestStack,
        private readonly string $dynamicQueryApiUrl,
        private readonly string $dynamicQueryApiIndexBattery,
        private readonly string $dynamicQueryApiIndexDelivery,
    ) {
    }

    /**
     * @throws DynamicQueryApiException
     */
    public function searchBatteries(array $dynRequest = ['filters' => []]): SearchResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new DynamicQueryApiException('Failed to search for batteries. No current request found.');
        }

        $authorizationHeader = $request->headers->get('Authorization');

        if (!$authorizationHeader) {
            throw new DynamicQueryApiException('Failed to search for batteries. No authorization header found.');
        }

        try {
            $response = $this->client->request(
                'POST',
                sprintf('%s/api/v1/search/%s', $this->dynamicQueryApiUrl, $this->dynamicQueryApiIndexBattery),
                [
                    'headers' => [
                        'Authorization' => $authorizationHeader,
                    ],
                    'json' => $dynRequest,
                ],
            );

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                throw new DynamicQueryApiException(sprintf(
                    'Failed to search for batteries. Status code: %d. Response: %s',
                    $response->getStatusCode(),
                    $response->getContent(false),
                ));
            }

            return $this->serializer->deserialize(
                $response->getContent(),
                SearchResponse::class,
                'json',
                [
                    SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Battery::class,
                ],
            );
        } catch (Exception $exception) {
            throw new DynamicQueryApiException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * @throws DynamicQueryApiException
     */
    public function searchBatteriesWithIds(string ...$batteryIds): SearchResponse
    {
        return $this->searchBatteries([
            'filters' => [
                [
                    'field' => '_id',
                    'type' => 'terms',
                    'values' => $batteryIds,
                ],
            ],
        ]);
    }

    /**
     * @throws DynamicQueryApiException
     */
    public function searchDeliveries(array $dynRequest = ['filters' => []]): SearchResponse
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new DynamicQueryApiException('Failed to search for deliveries. No current request found.');
        }

        $authorizationHeader = $request->headers->get('Authorization');

        if (!$authorizationHeader) {
            throw new DynamicQueryApiException('Failed to search for deliveries. No authorization header found.');
        }

        try {
            $response = $this->client->request(
                'POST',
                sprintf('%s/api/v1/search/%s', $this->dynamicQueryApiUrl, $this->dynamicQueryApiIndexDelivery),
                [
                    'headers' => [
                        'Authorization' => $authorizationHeader,
                    ],
                    'json' => $this->createSearchDeliveriesRequest($dynRequest),
                ],
            );

            if ($response->getStatusCode() !== Response::HTTP_OK) {
                throw new DynamicQueryApiException(sprintf(
                    'Failed to search for deliveries. Status code: %d. Response: %s',
                    $response->getStatusCode(),
                    $response->getContent(false),
                ));
            }

            return $this->serializer->deserialize(
                $response->getContent(),
                SearchResponse::class,
                'json',
                [
                    SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Delivery::class,
                ],
            );
        } catch (Exception $exception) {
            throw new DynamicQueryApiException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * @throws DynamicQueryApiException
     */
    public function searchDeliveriesWithIds(string ...$deliveryIds): SearchResponse
    {
        return $this->searchDeliveries([
            'filters' => [
                [
                    'field' => '_id',
                    'type' => 'terms',
                    'values' => $deliveryIds,
                ],
            ],
        ]);
    }

    private function createSearchDeliveriesRequest(array $dynRequest): array
    {
        static $defaultFilters = [
            'isDeleted' => ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true], 'not_included' => true],
        ];
        $filters = $dynRequest['filters'] ?? [];
        try {
            $filters = array_combine(array_column($filters, 'field'), $filters) ?: [];
        } catch (ValueError $error) {
            throw new InvalidArgumentException(
                '`filters` expected to have a `field` key each.',
                $error->getCode(),
                $error,
            );
        }
        $dynRequest['filters'] = array_values($filters + $defaultFilters);

        return $dynRequest;
    }
}
