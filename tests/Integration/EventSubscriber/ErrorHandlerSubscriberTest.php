<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\EventSubscriber\ErrorHandlerSubscriber;
use App\Logger\ExceptionContextLogger\ExceptionContextLoggerService;
use App\Responder\SerializerResponder;
use App\Tests\Traits\LoggerTestingTrait;
use Exception;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorHandlerSubscriberTest extends KernelTestCase
{
    use LoggerTestingTrait;

    /** @var ErrorHandlerSubscriber */
    private $subject;

    /** @var SerializerResponder|MockObject */
    private $responder;

    protected function setUp(): void
    {
        static::bootKernel();

        parent::setUp();

        $this->setUpTestLogHandler();

        $this->responder = $this->createMock(SerializerResponder::class);

        $this->subject = new ErrorHandlerSubscriber(
            new ExceptionContextLoggerService(
                static::getContainer()->get(LoggerInterface::class),
            ),
            $this->responder,
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(TranslatorInterface::class),
        );
    }

    public function testSubscribedEvents(): void
    {
        $this->assertSame(
            [
                KernelEvents::EXCEPTION => 'onKernelException',
                ConsoleErrorEvent::class => 'onConsoleErrorEvent',
            ],
            ErrorHandlerSubscriber::getSubscribedEvents(),
        );
    }

    public function testItDoesNotSetResponseOnSubRequests(): void
    {
        $event = new ExceptionEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Exception('Error'),
        );

        $this->subject->onKernelException($event);
        $trace = FlattenException::createFromThrowable($event->getThrowable())->toArray();
        $this->assertHasLogRecord(
            [
                'message' => 'Error',
                'context' => compact('trace'),
            ],
            Level::Error->value,
        );
    }

    public function testItSetsProperResponseFromResponderOnMasterRequest(): void
    {
        $expectedException = new Exception();
        $expectedResponse = new JsonResponse();

        $this->responder
            ->method('createErrorJsonResponse')
            ->with($expectedException)
            ->willReturn($expectedResponse);

        $event = new ExceptionEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $expectedException,
        );

        $this->subject->onKernelException($event);
        $trace = FlattenException::createFromThrowable($event->getThrowable())->toArray();
        $this->assertHasLogRecord(
            [
                'message' => $expectedException->getMessage(),
                'context' => compact('trace'),
            ],
            Level::Error->value,
        );
    }

    public function testItLogsTheCurrentAndPreviousException()
    {
        $previousException = new Exception('previous error');
        $currentException = new Exception('current error', 0, $previousException);

        $event = new ExceptionEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $currentException,
        );

        $this->subject->onKernelException($event);
        $trace = FlattenException::createFromThrowable($event->getThrowable())->toArray();
        $this->assertHasLogRecord(
            [
                'message' => $currentException->getMessage(),
                'context' => compact('trace'),
            ],
            Level::Error->value,
        );
    }

    public function testItLogsHttpExceptionsWithoutTraceWhenDebugModeIsDisabled(): void
    {
        $this->subject = new ErrorHandlerSubscriber(
            new ExceptionContextLoggerService(
                static::getContainer()->get(LoggerInterface::class),
            ),
            $this->responder,
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(TranslatorInterface::class),
        );

        $event = new ExceptionEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new ServiceUnavailableHttpException(),
        );

        $this->subject->onKernelException($event);
        $trace = FlattenException::createFromThrowable($event->getThrowable())->toArray();
        $this->assertHasLogRecord(
            [
                'message' => $event->getThrowable()->getMessage(),
                'context' => compact('trace'),
            ],
            Level::Error->value,
        );
    }

    public function testItSkipsHttpExceptions(): void
    {
        $this->subject = new ErrorHandlerSubscriber(
            new ExceptionContextLoggerService(
                static::getContainer()->get(LoggerInterface::class),
            ),
            $this->responder,
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(TranslatorInterface::class),
        );

        $event = new ExceptionEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(),
        );

        $this->subject->onKernelException($event);
        $this->assertEmpty($this->testLogHandler->getRecords());
    }

    public function testItLogsExceptionsWithBacktraceWhenConsideredAsCritical(): void
    {
        $this->subject = new ErrorHandlerSubscriber(
            static::getContainer()->get(ExceptionContextLoggerService::class),
            $this->responder,
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(TranslatorInterface::class),
        );

        $event = new ExceptionEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Exception(),
        );

        $this->subject->onKernelException($event);
        $trace = FlattenException::createFromThrowable($event->getThrowable())->toArray();
        $this->assertHasLogRecord(
            [
                'message' => $event->getThrowable()->getMessage(),
                'context' => compact('trace'),
            ],
            Level::Error->value,
        );
    }
}
