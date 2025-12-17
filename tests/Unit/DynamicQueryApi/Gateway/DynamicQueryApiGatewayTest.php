<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DynamicQueryApi\Gateway;

use App\DynamicQueryApi\Exception\DynamicQueryApiException;
use App\DynamicQueryApi\Gateway\DynamicQueryApiGateway;
use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\Delivery;
use App\DynamicQueryApi\Model\SearchResponse;
use App\DynamicQueryApi\Serializer\Denormalizer\SearchResponseDenormalizer;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DynamicQueryApiGatewayTest extends TestCase
{
    private DynamicQueryApiGateway $subject;
    private SerializerInterface|MockObject $serializerMock;
    private HttpClientInterface|MockObject $httpClientMock;
    private RequestStack|MockObject $requestStackMock;

    private const DYNAMIC_QUERY_API_URL = 'https://dynamic.query.api.url';
    private const DYNAMIC_QUERY_API_INDEX_BATTERY = 'batteryIndex';
    private const DYNAMIC_QUERY_API_INDEX_DELIVERY = 'deliveryIndex';

    protected function setUp(): void
    {
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);

        $this->subject = new DynamicQueryApiGateway(
            $this->serializerMock,
            $this->httpClientMock,
            $this->requestStackMock,
            self::DYNAMIC_QUERY_API_URL,
            self::DYNAMIC_QUERY_API_INDEX_BATTERY,
            self::DYNAMIC_QUERY_API_INDEX_DELIVERY,
        );
    }

    public function testSearchBatteries(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_BATTERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => []],
                ],
            )
            ->willReturn($responseMock);

        $searchResponseMock = $this->createMock(SearchResponse::class);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Battery::class])
            ->willReturn($searchResponseMock);

        $this->assertSame($searchResponseMock, $this->subject->searchBatteries());
    }

    public function testSearchBatteriesWhenStatusCodeIsNok(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_BATTERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => []],
                ],
            )
            ->willReturn($responseMock);

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize');

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('Failed to search for batteries. Status code: 500. Response: content');

        $this->subject->searchBatteries();
    }

    public function testSearchBatteriesWhenExceptionGetThrown(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_BATTERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => []],
                ],
            )
            ->willReturn($responseMock);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Battery::class])
            ->willThrowException(new Exception('reason'));

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('reason');

        $this->subject->searchBatteries();
    }

    public function testSearchBatteriesWithoutCurrentRequest(): void
    {
        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('Failed to search for batteries. No current request found.');

        $this->subject->searchBatteries();
    }

    public function testSearchBatteriesWithoutAuthorizationHeaderInTheCurrentRequest(): void
    {
        $request = new Request();

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('Failed to search for batteries. No authorization header found.');

        $this->subject->searchBatteries();
    }

    public function testSearchBatteriesWithIds(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_BATTERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => [
                        [
                            'field' => '_id',
                            'type' => 'terms',
                            'values' => ['id1', 'id2'],
                        ],
                    ]],
                ],
            )
            ->willReturn($responseMock);

        $searchResponseMock = $this->createMock(SearchResponse::class);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Battery::class])
            ->willReturn($searchResponseMock);

        $this->assertSame($searchResponseMock, $this->subject->searchBatteriesWithIds('id1', 'id2'));
    }

    public function testSearchDeliveries(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_DELIVERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => [
                        ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true], 'not_included' => true],
                    ]],
                ],
            )
            ->willReturn($responseMock);

        $searchResponseMock = $this->createMock(SearchResponse::class);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Delivery::class])
            ->willReturn($searchResponseMock);

        $this->assertSame($searchResponseMock, $this->subject->searchDeliveries());
    }

    public function testSearchDeliveriesWithOverriddenDefaultFilters(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_DELIVERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => [
                        ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true]],
                    ]],
                ],
            )
            ->willReturn($responseMock);

        $searchResponseMock = $this->createMock(SearchResponse::class);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Delivery::class])
            ->willReturn($searchResponseMock);

        $this->assertSame(
            $searchResponseMock,
            $this->subject->searchDeliveries(
                ['filters' => [['field' => 'isDeleted', 'type' => 'terms', 'values' => [true]]]],
            ),
        );
    }

    public function testSearchDeliveriesWhenStatusCodeIsNok(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(500);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_DELIVERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => [
                        ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true], 'not_included' => true],
                    ]],
                ],
            )
            ->willReturn($responseMock);

        $this->serializerMock
            ->expects($this->never())
            ->method('deserialize');

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('Failed to search for deliveries. Status code: 500. Response: content');

        $this->subject->searchDeliveries();
    }

    public function testSearchDeliveriesWhenExceptionGetThrown(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_DELIVERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => [
                        ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true], 'not_included' => true],
                    ]],
                ],
            )
            ->willReturn($responseMock);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Delivery::class])
            ->willThrowException(new Exception('reason'));

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('reason');

        $this->subject->searchDeliveries();
    }

    public function testSearchDeliveriesWithFilters(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('`filters` expected to have a `field` key each.');

        $this->subject->searchDeliveries(['filters' => [
            ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true]],
            ['type' => 'terms', 'values' => [false]],
        ]]);
    }

    public function testSearchDeliveriesWithoutCurrentRequest(): void
    {
        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('Failed to search for deliveries. No current request found.');

        $this->subject->searchDeliveries();
    }

    public function testSearchDeliveriesWithoutAuthorizationHeaderInTheCurrentRequest(): void
    {
        $request = new Request();

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->expectException(DynamicQueryApiException::class);
        $this->expectExceptionMessage('Failed to search for deliveries. No authorization header found.');

        $this->subject->searchDeliveries();
    }

    public function testSearchDeliveriesWithIds(): void
    {
        $authorizationHeader = 'Bearer token';

        $request = new Request();
        $request->headers->set('Authorization', $authorizationHeader);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $responseMock
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('content');

        $this->httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                sprintf('%s/api/v1/search/%s', self::DYNAMIC_QUERY_API_URL, self::DYNAMIC_QUERY_API_INDEX_DELIVERY),
                [
                    'headers' => ['Authorization' => $authorizationHeader],
                    'json' => ['filters' => [
                        [
                            'field' => '_id',
                            'type' => 'terms',
                            'values' => ['id1', 'id2'],
                        ],
                        ['field' => 'isDeleted', 'type' => 'terms', 'values' => [true], 'not_included' => true],
                    ]],
                ],
            )
            ->willReturn($responseMock);

        $searchResponseMock = $this->createMock(SearchResponse::class);

        $this->serializerMock
            ->expects($this->once())
            ->method('deserialize')
            ->with('content', SearchResponse::class, 'json', [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Delivery::class])
            ->willReturn($searchResponseMock);

        $this->assertSame($searchResponseMock, $this->subject->searchDeliveriesWithIds('id1', 'id2'));
    }
}
