<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DynamicQueryApi\Serializer\Denormalizer;

use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\SearchResponse;
use App\DynamicQueryApi\Serializer\Denormalizer\SearchResponseDenormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SearchResponseDenormalizerTest extends TestCase
{
    private SearchResponseDenormalizer $subject;
    private DenormalizerInterface|MockObject $denormalizerMock;

    protected function setUp(): void
    {
        $this->denormalizerMock = $this->createMock(DenormalizerInterface::class);
        $this->subject = new SearchResponseDenormalizer();

        $this->subject->setDenormalizer($this->denormalizerMock);
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->subject->supportsDenormalization('data', SearchResponse::class));
    }

    public function testDenormalize(): void
    {
        $this->denormalizerMock
            ->expects($this->once())
            ->method('denormalize')
            ->with(['foo'], 'App\DynamicQueryApi\Model\Battery[]', 'json')
            ->willReturn(['denormalizedData']);

        $searchResponse = $this->subject->denormalize(
            $this->getNormalizedData(),
            SearchResponse::class,
            context: [SearchResponseDenormalizer::CONTEXT_DATA_TYPE => Battery::class],
        );

        $this->assertSame(['denormalizedData'], $searchResponse->getData());
        $this->assertSame(1, $searchResponse->getTotalResults());
        $this->assertSame(['bar'], $searchResponse->getLastId());
    }

    public function testDenormalizeIfDataIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot denormalize data into App\DynamicQueryApi\Model\SearchResponse: data is not an array');

        $this->subject->denormalize('foo', SearchResponse::class);
    }

    /**
     * @dataProvider arrayKeyProvider
     */
    public function testDenormalizeIfMandatoryArrayKeyIsMissing(string $key): void
    {
        $normalizedData = $this->getNormalizedData();

        unset($normalizedData[$key]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot denormalize data into App\DynamicQueryApi\Model\SearchResponse: the following mandatory keys are missing: %s',
            $key,
        ));

        $this->subject->denormalize($normalizedData, SearchResponse::class);
    }

    public function testDenormalizeIfContextDataTypeIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dataType context parameter is missing for App\DynamicQueryApi\Serializer\Denormalizer\SearchResponseDenormalizer');

        $this->subject->denormalize($this->getNormalizedData(), SearchResponse::class);
    }

    public function arrayKeyProvider(): array
    {
        return [
            ['data'],
            ['totalResults'],
            ['lastId'],
        ];
    }

    private function getNormalizedData(): array
    {
        return [
            'data' => ['foo'],
            'totalResults' => 1,
            'lastId' => ['bar'],
        ];
    }
}
