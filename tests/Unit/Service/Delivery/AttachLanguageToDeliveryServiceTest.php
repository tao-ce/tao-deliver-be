<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Factory\ExceptionConverterFactory;
use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentMessage;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Qti\Extractor\QtiPackageExtractor;
use App\Repository\DeliveryRepository;
use App\Service\Delivery\AttachLanguageToDeliveryService;
use App\Service\Delivery\DeleteDeliveryService;
use App\Validator\Locale\LocaleValidator;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

class AttachLanguageToDeliveryServiceTest extends TestCase
{
    private AttachLanguageToDeliveryService $subject;
    private readonly DeliveryRepository $deliveryRepositoryMock;
    private readonly MessageBusInterface $messageBusMock;
    private readonly LocaleValidator $localeValidatorMock;
    private readonly LoggerInterface $loggerMock;
    private readonly QtiPackageExtractor $packageExtractorMock;
    private readonly QtiPackageCompiler $packageCompilerMock;
    private readonly FilesystemOperator $base64ZipStorageMock;
    private readonly FilesystemReader $preuploadedPackageStorageMock;
    private readonly FilesystemReader $dataStoreDeliveryProcessingInputMock;
    private readonly LockFactory $lockFactoryMock;
    private readonly DeleteDeliveryService $deleteDeliveryService;
    private readonly ExceptionConverterFactory $excepionConverterFactory;
    private readonly EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->localeValidatorMock = $this->createMock(LocaleValidator::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->packageExtractorMock = $this->createMock(QtiPackageExtractor::class);
        $this->packageCompilerMock = $this->createMock(QtiPackageCompiler::class);
        $this->base64ZipStorageMock = $this->createMock(FilesystemOperator::class);
        $this->preuploadedPackageStorageMock = $this->createMock(FilesystemReader::class);
        $this->dataStoreDeliveryProcessingInputMock = $this->createMock(FilesystemReader::class);
        $this->lockFactoryMock = $this->createMock(LockFactory::class);
        $this->deleteDeliveryService = $this->createMock(DeleteDeliveryService::class);
        $this->excepionConverterFactory = $this->createMock(ExceptionConverterFactory::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);

        $this->subject = new AttachLanguageToDeliveryService(
            $this->deliveryRepositoryMock,
            $this->messageBusMock,
            $this->localeValidatorMock,
            $this->loggerMock,
            $this->packageExtractorMock,
            $this->packageCompilerMock,
            $this->base64ZipStorageMock,
            $this->preuploadedPackageStorageMock,
            $this->dataStoreDeliveryProcessingInputMock,
            $this->lockFactoryMock,
            $this->deleteDeliveryService,
            $this->excepionConverterFactory,
            $this->eventDispatcher,
        );
    }

    public function testWhenLocaleValidationFailsThenExceptionIsThrown(): void
    {
        $deliveryMock = $this->createMock(Delivery::class);
        $this->deliveryRepositoryMock->expects($this->once())
            ->method('find')
            ->willReturn($deliveryMock);

        $this->localeValidatorMock->expects($this->once())
            ->method('validate')
            ->with('en')
            ->willThrowException(new RuntimeException('Invalid locale.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid locale.');

        $this->subject->attach('deliveryId', 'en', 'base64Content', null);
    }

    public function testWhenLocaleAlreadyExistsThenExceptionIsThrown(): void
    {
        $deliveryMock = $this->createMock(Delivery::class);
        $deliveryMock->expects($this->once())
            ->method('isSupportedLocale')
            ->with('en')
            ->willReturn(true);

        $this->deliveryRepositoryMock->expects($this->once())
            ->method('find')
            ->willReturn($deliveryMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Delivery with ID [deliveryId] already contains locale [en].');

        $this->subject->attach('deliveryId', 'en', 'base64Content', null);
    }

    public function testSuccessfulLocaleAttachmentDispatchesMessage(): void
    {
        $deliveryMock = $this->createMock(Delivery::class);
        $deliveryMock->expects($this->once())
            ->method('isSupportedLocale')
            ->with('en')
            ->willReturn(false);

        $this->deliveryRepositoryMock->expects($this->once())
            ->method('find')
            ->willReturn($deliveryMock);

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DeliveryLanguageAttachmentMessage::class));

        $result = $this->subject->attach('deliveryId', 'en', 'base64Content', null);
        $this->assertInstanceOf(Delivery::class, $result);
    }
}
