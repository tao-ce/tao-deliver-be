<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use League\Flysystem\FilesystemWriter;
use League\Flysystem\UnableToDeleteDirectory;
use Psr\Log\LoggerInterface;

readonly class DeliveryExecutionUploadsManagerService
{
    public function __construct(
        private FilesystemWriter $deliveryExecutionUploadsStorage,
        private LoggerInterface $auditPlatformLogger,
    ) {
    }

    public function dropUploads(string $deliveryExecutionId): void
    {
        try {
            $this->deliveryExecutionUploadsStorage->deleteDirectory($deliveryExecutionId);
            $this->auditPlatformLogger->info(
                sprintf(
                    'DeliveryExecution [%s]: Test uploads successfully deleted.',
                    $deliveryExecutionId,
                ),
            );
        } catch (UnableToDeleteDirectory $e) {
            $this->auditPlatformLogger->info(
                sprintf(
                    'Skipped: No files found for deliveryExecution [%s]. with message: %s',
                    $deliveryExecutionId,
                    $e->getMessage(),
                ),
            );
        }
    }
}
