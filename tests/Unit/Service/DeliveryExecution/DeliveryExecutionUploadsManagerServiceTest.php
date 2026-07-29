<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025-2026 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Service\DeliveryExecution;

use App\Service\DeliveryExecution\DeliveryExecutionUploadsManagerService;
use League\Flysystem\FilesystemWriter;
use League\Flysystem\UnableToDeleteDirectory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeliveryExecutionUploadsManagerServiceTest extends TestCase
{
    private FilesystemWriter|MockObject $storage;
    private LoggerInterface|MockObject $logger;
    private DeliveryExecutionUploadsManagerService $subject;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(FilesystemWriter::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new DeliveryExecutionUploadsManagerService(
            $this->storage,
            $this->logger,
        );
    }

    public function testDropUploadsDeletesDirectoryAndLogsSuccess(): void
    {
        $id = 'delivery-123';

        // Expect the directory to be deleted
        $this->storage->expects($this->once())
            ->method('deleteDirectory')
            ->with($id);

        // Expect the success log message
        $this->logger->expects($this->once())
            ->method('info')
            ->with(sprintf('DeliveryExecution [%s]: Test uploads successfully deleted.', $id));

        $this->subject->dropUploads($id);
    }

    public function testDropUploadsHandlesExceptionAndLogsSkippedStatus(): void
    {
        $id = 'delivery-456';
        $errorMessage = 'Directory not found';

        // Simulate Flysystem exception
        $exception = UnableToDeleteDirectory::atLocation($id, $errorMessage);

        $this->storage->expects($this->once())
            ->method('deleteDirectory')
            ->with($id)
            ->willThrowException($exception);

        // Expect the failure log message (catch block)
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains("Skipped: No files found for deliveryExecution [$id]"));

        $this->subject->dropUploads($id);
    }
}
