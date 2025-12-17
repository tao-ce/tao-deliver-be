<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Handler;

use App\Domain\Publication\Model\Publication;
use App\Generator\UuidGenerator;
use App\Messenger\Handler\PublicationMessageHandler;
use App\Messenger\Message\PublicationMessage;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Qti\Extractor\QtiPackageExtractor;
use App\Repository\DeliveryRepository;
use App\Repository\PublicationRepository;
use App\Tests\Traits\DomainTestingTrait;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PublicationMessageHandlerTest extends TestCase
{
    use DomainTestingTrait;

    private readonly PublicationMessageHandler $subject;
    private readonly PublicationRepository $publicationRepositoryMock;
    private readonly DeliveryRepository $deliveryRepositoryMock;
    private readonly FileSystemReader $packageStorageMock;

    public function setUp(): void
    {
        $this->publicationRepositoryMock = $this->createMock(PublicationRepository::class);
        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $this->packageStorageMock = $this->createMock(FilesystemReader::class);

        $this->deliveryRepositoryMock
            ->method('find')
            ->willThrowException(new DocumentNotFoundException());

        $this->subject = new PublicationMessageHandler(
            $this->createMock(QtiPackageExtractor::class),
            $this->createMock(QtiPackageCompiler::class),
            $this->publicationRepositoryMock,
            $this->deliveryRepositoryMock,
            $this->createMock(UuidGenerator::class),
            $this->createMock(FilesystemOperator::class),
            $this->packageStorageMock,
            $this->packageStorageMock,
            $this->createMock(WorkflowInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(MessageBus::class),
        );
    }

    public function testWhenNeitherPackageContentsNorRefProvidedThenExceptionIsThrown()
    {
        $this->mockPublicationWithPackageRef('', '');
        $this->expectException(RuntimeException::class);

        $this->subject->__invoke($this->createMock(PublicationMessage::class));
    }

    public function testWhenPackageRefProvidedThenItIsDownloaded()
    {
        $this->mockPublicationWithPackageRef();
        $this->packageStorageMock->expects($this->once())->method('read')->willReturn('content');

        $this->subject->__invoke($this->createMock(PublicationMessage::class));
    }

    public function testWhenPackageRefIsMalformedThenExceptionIsThrown()
    {
        $this->mockPublicationWithPackageRef('', 'host:65536');
        $this->expectException(RuntimeException::class);

        $this->subject->__invoke($this->createMock(PublicationMessage::class));
    }

    private function mockPublicationWithPackageRef($packagePath = '', $packageRef = 'http://package.test/location'): void
    {
        $publication = $this->createTestPublication(
            Publication::STATUS_CREATED,
            'id',
            'tenantId',
            $packagePath,
            $packageRef,
        );
        $this->publicationRepositoryMock->expects($this->once())->method('find')->willReturn($publication);
    }
}
