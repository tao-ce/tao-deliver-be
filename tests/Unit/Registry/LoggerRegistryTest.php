<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Registry;

use App\Registry\LoggerRegistry;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoggerRegistryTest extends TestCase
{
    /** @var LoggerInterface|MockObject */
    private $loggerInterfaceMock;

    /** @var Logger[] */
    private $loggerMocks;

    /** @var LoggerRegistry */
    private $subject;

    protected function setUp(): void
    {
        $this->loggerInterfaceMock = $this->createMock(LoggerInterface::class);

        $this->loggerMocks = [
            'foo' => $this->getLoggerMock('foo'),
            'bar' => $this->getLoggerMock('bar'),
        ];

        $this->subject = new LoggerRegistry($this->loggerInterfaceMock, $this->loggerMocks);
    }

    public function testGetLoggerForChannel(): void
    {
        /** @var Logger $result */
        $result = $this->subject->getLoggerForChannel('bar');

        $this->assertEquals($this->loggerMocks['bar'], $result);
        $this->assertEquals('bar', $result->getName());
    }

    public function testGetLoggerForChannelReturnsDefaultWhenParameterNull(): void
    {
        $this->assertEquals($this->loggerInterfaceMock, $this->subject->getLoggerForChannel());
    }

    public function testGetLoggerForChannelWhenParameterInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot find logger for "test" channel');

        $this->subject->getLoggerForChannel('test');
    }

    public function testGetLoggerForChannelReturnsDefaultWhenParameterDefault(): void
    {
        $this->assertEquals($this->loggerInterfaceMock, $this->subject->getLoggerForChannel('default'));
    }

    public function testGetAvailableChannels(): void
    {
        $result = $this->subject->getAvailableChannels();

        $this->assertEquals('default', $result[0]);
        $this->assertEquals('foo', $result[1]);
        $this->assertEquals('bar', $result[2]);
    }

    private function getLoggerMock(string $name): Logger
    {
        $loggerMock = $this->createMock(Logger::class);

        $loggerMock
            ->method('getName')
            ->willReturn($name);

        return $loggerMock;
    }
}
