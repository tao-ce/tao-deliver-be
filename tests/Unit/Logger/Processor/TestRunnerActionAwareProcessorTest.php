<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Logger\Processor;

use App\Logger\Processor\TestRunnerActionAwareProcessor;
use App\TestRunner\Service\ActionIdProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Monolog\LogRecord;
use Monolog\Level;
use DateTimeImmutable;

class TestRunnerActionAwareProcessorTest extends TestCase
{
    private TestRunnerActionAwareProcessor $subject;
    private ActionIdProvider|MockObject $actionIdProviderMock;

    protected function setUp(): void
    {
        $this->actionIdProviderMock = $this->createMock(ActionIdProvider::class);

        $this->subject = new TestRunnerActionAwareProcessor($this->actionIdProviderMock);
    }

    public function testActionIdProvided(): void
    {
        $actionId = 'actionId';
        $this->actionIdProviderMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($actionId);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: '',
        );

        $result = ($this->subject)($record);
        self::assertEquals(['actionId' => $actionId], $result->extra);
    }

    public function testActionIdNotProvided(): void
    {

        $this->actionIdProviderMock
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: '',
        );

        $result = ($this->subject)($record);
        self::assertEquals($record, $result);
    }
}
