<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Factory;

use App\Factory\ExceptionConverterFactory;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentDriverException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use PHPUnit\Framework\TestCase;

class ExceptionConverterFactoryTest extends TestCase
{
    private ExceptionConverterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ExceptionConverterFactory();
    }

    public function testConvertWithDocumentNormalizerException(): void
    {
        $exception = new DocumentNormalizerException('Document normalizer error', 501);
        $result = $this->factory->convert($exception);

        $this->assertEquals(102, $result['code']);
        $this->assertEquals(501, $result['exceptionCode']);
        $this->assertEquals(DocumentNormalizerException::class, $result['type']);
        $this->assertEquals('Document normalizer error', $result['message']);
        $this->assertIsArray($result['trace']);
    }

    public function testConvertWithDocumentDriverException(): void
    {
        $exception = new DocumentDriverException('Document driver error', 502);
        $result = $this->factory->convert($exception);

        $this->assertEquals(103, $result['code']);
        $this->assertEquals(502, $result['exceptionCode']);
        $this->assertEquals(DocumentDriverException::class, $result['type']);
        $this->assertEquals('Document driver error', $result['message']);
        $this->assertIsArray($result['trace']);
    }

    public function testConvertWithUnhandledException(): void
    {
        $exception = new Exception('Other code error', 503);
        $result = $this->factory->convert($exception);

        $this->assertEquals(500, $result['code']);
        $this->assertEquals(503, $result['exceptionCode']);
        $this->assertEquals(Exception::class, $result['type']);
        $this->assertEquals('Other code error', $result['message']);
        $this->assertIsArray($result['trace']);
    }

    public function testConvertWithCustomHandler(): void
    {
        $mockFactory = $this->getMockBuilder(ExceptionConverterFactory::class)
            ->addMethods(['handleDocumentDriverException'])
            ->getMock();

        $mockFactory->method('handleDocumentDriverException')->willReturn(999);

        $exception = new DocumentDriverException();

        $result = $mockFactory->convert($exception);

        $this->assertEquals(999, $result['code']);
    }
}
