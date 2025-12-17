<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Service\Publication;

use App\Domain\Publication\Model\Publication;
use App\Generator\UuidGenerator;
use App\Messenger\Message\PublicationMessage;
use App\Repository\PublicationRepository;
use App\Service\Publication\CreatePublicationService;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Traits\FilesystemTrait;
use Exception;
use League\Flysystem\FilesystemWriter;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class CreatePublicationServiceTest extends KernelTestCase
{
    use LoggerTestingTrait;
    use MessengerTestingTrait;
    use DocumentTestingTrait;
    use FilesystemTrait;

    /** @var CreatePublicationService */
    private $subject;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestMessageBus();
        $this->setUpTestDocumentManager();

        $this->subject = static::getContainer()->get(CreatePublicationService::class);
    }

    public function testBase64FileNameConsistency(): void
    {
        $this->assertEquals('base64PackageContent.txt', CreatePublicationService::BASE_64_FILE_NAME);
    }

    public function testPublicationCreationSuccess(): void
    {
        $base64PackageContent = 'content';
        $packageRef = '';
        $tenantId = 'tenantId';
        $packageConfiguration = ['label' => "configuration with single'quote"];
        $storage = static::getContainer()->get('base64_zip.storage');

        $publication = $this->subject->create(
            $base64PackageContent,
            $packageRef,
            $tenantId,
            $packageConfiguration,
        );

        $this->assertEquals(Publication::STATUS_CREATED, $publication->getStatus());
        $this->assertEquals($tenantId, $publication->getTenantId());
        $this->assertEquals($packageConfiguration, $publication->getPackageConfiguration());

        $expectedPath = $this->buildPathFor($publication->getId(), CreatePublicationService::BASE_64_FILE_NAME);
        $this->assertEquals($expectedPath, $publication->getPackagePath());
        $this->assertTrue($storage->has($expectedPath));
        $this->assertEquals($base64PackageContent, $storage->read($expectedPath));

        $this->assertHasDocumentWithId(Publication::class, $publication->getId());

        /** @var Publication $storedPublication */
        $storedPublication = $this->findDocumentById(Publication::class, $publication->getId());

        $this->assertEquals($publication->getId(), $storedPublication->getId());
        $this->assertEquals($publication->getTenantId(), $storedPublication->getTenantId());
        $this->assertEquals($publication->getStatus(), $storedPublication->getStatus());
        $this->assertEquals($publication->getPackageConfiguration(), $storedPublication->getPackageConfiguration());
        $this->assertEquals($publication->getPackagePath(), $storedPublication->getPackagePath());
        $this->assertEquals($publication->getReports(), $storedPublication->getReports());

        $this->assertCountTransportMessages('publication', 1);

        /** @var PublicationMessage $publishedMessage */
        $publishedMessage = current($this->getTransportMessages('publication'))->getMessage();

        $this->assertHasTransportMessage('publication', PublicationMessage::class);
        $this->assertEquals($tenantId, $publishedMessage->getTenantId());
        $this->assertEquals([], $publishedMessage->getConfiguration());
        $this->assertEquals($expectedPath, $publishedMessage->getBase64ZipPath());

        $this->assertHasLogRecordWithMessage(
            sprintf('[%s] - Publication was created with success', $storedPublication->getId()),
            Logger::INFO,
            'audit_platform',
        );

        $this->assertEquals($storedPublication, current($this->getLogRecords('audit_platform'))['context']['publication']);
    }

    public function testPublicationCreationFailure(): void
    {
        $storageMock = $this->createMock(FilesystemWriter::class);

        $storageMock
            ->expects($this->once())
            ->method('write')
            ->willThrowException(new Exception('custom error'));

        $subject = new CreatePublicationService(
            static::getContainer()->get(UuidGenerator::class),
            $storageMock,
            static::getContainer()->get(PublicationRepository::class),
            static::getContainer()->get(MessageBusInterface::class),
            static::getContainer()->get('monolog.logger.audit_platform'),
            static::getContainer()->get(LoggerInterface::class),
        );

        try {
            $subject->create('packageContent', '', 'tenantId', []);

            $this->fail('CreatePublicationService should have fail');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(Exception::class, $exception);
            $this->assertEquals('custom error', $exception->getMessage());
        }
    }
}
