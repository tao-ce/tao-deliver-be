<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Publication;

use App\Domain\Publication\Model\Publication;
use App\Generator\UuidGenerator;
use App\Messenger\Message\PublicationMessage;
use App\Repository\PublicationRepository;
use App\Traits\FilesystemTrait;
use League\Flysystem\FilesystemWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CreatePublicationService
{
    use FilesystemTrait;

    public const BASE_64_FILE_NAME = 'base64PackageContent.txt';

    public function __construct(
        private UuidGenerator $uuidGenerator,
        private FilesystemWriter $base64ZipStorage,
        private PublicationRepository $repository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $auditPlatformLogger,
        private LoggerInterface $logger,
    ) {
    }

    public function create(
        string $base64PackageContent,
        string $packageRef,
        string $tenantId,
        array $packageConfiguration,
        ?string $deliveryId = null,
        ?string $locale = null,
        array $translations = [],
    ): Publication {
        $publicationId = $this->uuidGenerator->generate();

        $packagePath = '';
        if (!empty($base64PackageContent)) {
            $packagePath = $this->buildPathFor($publicationId, static::BASE_64_FILE_NAME);
            $this->base64ZipStorage->write($packagePath, $base64PackageContent);
        }

        $publication = new Publication(
            $publicationId,
            $tenantId,
            $packagePath,
            $packageRef,
            $packageConfiguration,
            [],
            Publication::STATUS_CREATED,
            $deliveryId,
            $locale,
        );
        $this->repository->save($publication);

        $this->messageBus->dispatch($this->createMessageFromPublication($publication, $translations));

        $this->auditPlatformLogger->info(
            sprintf('[%s] - Publication was created with success', $publicationId),
            ['publication' => $publication],
        );

        return $publication;
    }

    private function createMessageFromPublication(
        Publication $publication,
        array $translations = [],
    ): PublicationMessage {
        // label with special characters could break test compilation, 'unset' is used as a workaround
        $packageConfiguration = $publication->getPackageConfiguration();
        unset($packageConfiguration['label']);

        return new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $packageConfiguration,
            $publication->getDeliveryId(),
            $translations,
        );
    }
}
