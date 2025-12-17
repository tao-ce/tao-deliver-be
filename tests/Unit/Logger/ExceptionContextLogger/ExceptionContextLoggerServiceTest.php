<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Logger\ExceptionContextLogger;

use App\Logger\ExceptionContextLogger\ExceptionContextLoggerService;
use App\Tests\Traits\LoggerTestingTrait;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * @author Kiryl Poyu - kyril.poyu@taotesting.com
 */
class ExceptionContextLoggerServiceTest extends KernelTestCase
{
    use LoggerTestingTrait;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
        $this->setUpTestLogHandler();

        $this->logger = static::getContainer()->get(LoggerInterface::class);
    }

    public function testSkipLoggingHttpExceptionInterface(): void
    {
        $subject = $this->buildSubjectInstance();
        $throwable = new BadRequestHttpException('Bad Request');

        $subject->logException($throwable);
        $this->assertEmpty($this->testLogHandler->getRecords());
    }


    public function testLogHttpExceptionInterfaceWhenInternalServerError(): void
    {
        $subject = $this->buildSubjectInstance();
        $throwable = new ServiceUnavailableHttpException();

        $subject->logException($throwable);
        $this->assertCount(1, $this->testLogHandler->getRecords());

        $this->assertHasNoLogRecordWithMessage($throwable->getMessage(), Logger::CRITICAL);
    }

    public function testLogNotHttpExceptionInterface(): void
    {
        $subject = $this->buildSubjectInstance();
        $throwable = new Exception('Simple Exception');

        $subject->logException($throwable);
        $this->assertCount(1, $this->testLogHandler->getRecords());
    }

    public function testLogFullExceptionTrace(): void
    {
        $subject = $this->buildSubjectInstance();
        $previous = new Exception('Internal Exception');
        $throwable = new Exception('External Exception', previous: $previous);

        $subject->logException($throwable);
        $records = $this->testLogHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('External Exception', $records[0]->context['trace'][0]['message']);
        $this->assertSame('Internal Exception', $records[0]->context['trace'][1]['message']);
    }


    private function buildSubjectInstance(): ExceptionContextLoggerService
    {
        return new ExceptionContextLoggerService($this->logger);
    }
}
