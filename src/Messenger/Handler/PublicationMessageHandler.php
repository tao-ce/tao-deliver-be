<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Delivery\Event\DeliveryCreatedEvent;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\Publication\Model\Publication;
use App\Generator\UuidGenerator;
use App\Messenger\Message\Delivery\DeliveryPublicationFailedMessage;
use App\Messenger\Message\Delivery\SynchronousDeliveryLanguageAttachmentMessage;
use App\Messenger\Message\PublicationMessage;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Qti\Extractor\QtiPackageExtractor;
use App\Repository\DeliveryRepository;
use App\Repository\PublicationRepository;
use Carbon\Carbon;
use Exception;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
class PublicationMessageHandler
{
    public function __construct(
        private QtiPackageExtractor $packageExtractor,
        private QtiPackageCompiler $packageCompiler,
        private PublicationRepository $publicationRepository,
        private DeliveryRepository $deliveryRepository,
        private UuidGenerator $uuidGenerator,
        private FilesystemOperator $base64ZipStorage,
        private FilesystemReader $preuploadedPackageStorage,
        private FilesystemReader $dataStoreDeliveryProcessingInput,
        private WorkflowInterface $workflow,
        private LoggerInterface $auditPlatformLogger,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PublicationMessage $message): void
    {
        $publication = $this->publicationRepository->find($message->getPublicationId());
        $this->auditPlatformLogger->info(sprintf('[%s] Publication process started', $publication->getId()));

        try {
            $this->applyPublicationStateTransition($publication, 'process_start');
        } catch (NotEnabledTransitionException $exception) {
            $this->auditPlatformLogger->warning(
                sprintf(
                    '[%s] Publication process already started, potential retry was proceeded',
                    $publication->getId(),
                ),
            );
            $this->applyPublicationStateTransition($publication, 'process_failure');
            throw $exception;
        }

        $deliveryId = $publication->getDeliveryId() ?? $this->uuidGenerator->generateMedium();

        if ($this->hasPublishedDelivery($deliveryId)) {
            $this->applyPublicationStateTransition($publication, 'process_failure');

            return;
        }

        try {
            $zipPackage = $this->retrieveTestPackageArchive($publication);

            $compilationResult = $this->packageCompiler->compile(
                $deliveryId,
                $this->packageExtractor->extract($zipPackage, $deliveryId),
                $publication->getTenantId(),
                null,
            );

            $this->auditPlatformLogger->info(
                sprintf('[%s] Delivery package was extracted and compilation started', $publication->getId()),
            );

            $publication->setReports($compilationResult->getCompilationReports());

            if ($compilationResult->isSuccessful()) {
                $this->auditPlatformLogger->info(
                    sprintf('[%s] Delivery was compiled successful', $publication->getId()),
                );

                $delivery = $this->createDelivery(
                    $deliveryId,
                    $publication->getTenantId(),
                    $compilationResult->getCompiledCompactTestFilePath(),
                    $publication->getPackageConfiguration(),
                    $compilationResult->getAssessmentItemRefMapping(),
                    $publication->getPackageRef(),
                    $publication->getLocale(),
                    $message->getTranslations(),
                );

                $this->auditPlatformLogger->info(
                    sprintf('[%s] Delivery was created with id:[%s]', $publication->getId(), $delivery->getId()),
                );

                $publication->setDeliveryId($delivery->getId());

                $this->applyPublicationStateTransition($publication, 'process_success');

                $this->auditPlatformLogger->info(
                    sprintf('[%s] Publication process ended with success', $publication->getId()),
                );

                try {
                    $this->processTranslations($delivery);
                } catch (Exception $exception) {
                    $this->auditPlatformLogger->info(
                        sprintf(
                            '[%s] Error during scheduling locale attachment: [%s]',
                            $publication->getId(),
                            $exception->getMessage(),
                        ),
                    );
                }
            } else {
                $this->auditPlatformLogger->info(
                    sprintf('[%s] Publication process ended with failure', $publication->getId()),
                );
                $this->applyPublicationStateTransition($publication, 'process_failure');
            }

            if ($publication->getPackagePath()) {
                $this->base64ZipStorage->delete($publication->getPackagePath());
                $this->auditPlatformLogger->info(
                    sprintf(
                        '[%s] Publication process cleanup removed the encoded package at path: %s',
                        $publication->getId(),
                        $publication->getPackagePath(),
                    ),
                );
            }
        } catch (Exception $exception) {
            $this->applyPublicationStateTransition($publication, 'process_failure');

            throw $exception;
        }
    }

    private function applyPublicationStateTransition(
        Publication $publication,
        string $transition,
    ): void {
        $this->workflow->apply($publication, $transition);
        $this->publicationRepository->save($publication);

        if ($transition == 'process_failure') {
            $this->messageBus->dispatch(
                new DeliveryPublicationFailedMessage(
                    $publication->getDeliveryId(),
                    $publication->getTenantId(),
                    $publication->getPackageConfiguration(),
                ),
            );
        }
    }

    private function createDelivery(
        string $deliveryId,
        string $tenantId,
        string $compactTestFilePath,
        array $configuration,
        array $qtiItemsMapping,
        ?string $packageRef,
        ?string $locale,
        array $translations,
    ): Delivery {
        $delivery = new Delivery(
            $deliveryId,
            $tenantId,
            Carbon::now(),
            $compactTestFilePath,
            $configuration,
            $qtiItemsMapping,
            $packageRef,
            false,
            null,
            $locale,
            $this->getSupportedLocales($locale, $configuration),
            $translations,
        );

        $this->deliveryRepository->save($delivery);

        return $delivery;
    }

    /**
     * @param Publication $publication
     * @return string
     * @throws UnableToReadFile
     */
    private function retrieveTestPackageArchive(Publication $publication): string
    {
        if (!empty($publication->getPackagePath())) {
            return base64_decode($this->base64ZipStorage->read($publication->getPackagePath()));
        }

        if (empty($publication->getPackageRef())) {
            $this->auditPlatformLogger->info(
                sprintf('[%s] Publication aborted. Package was not provided', $publication->getId()),
            );
            throw new RuntimeException('Neither test package nor reference provided, publication aborted.');
        }

        $fileContents = @file_get_contents($publication->getPackageRef());
        if ($fileContents) {
            return $fileContents;
        }

        $configuration = $publication->getPackageConfiguration();

        if ($configuration['metadata']['portalImport'] ?? false) {
            $this->auditPlatformLogger->info(
                sprintf('Trying to read [%s] from DataStore', $publication->getPackageRef()),
            );
            return $this->dataStoreDeliveryProcessingInput->read(
                $publication->getPackageRef(),
            );
        }

        $packageRef = $this->parseObjectKey($publication->getPackageRef());
        $this->auditPlatformLogger->info(
            sprintf('Trying to read [%s] from Pre Uploaded Storage', $packageRef),
        );

        return $this->preuploadedPackageStorage->read(
            $packageRef,
        );
    }

    private function parseObjectKey(string $packageRef): string
    {
        $urlPath = parse_url($packageRef, PHP_URL_PATH);
        if (empty($urlPath)) {
            throw new RuntimeException('Provided packageRef parameter is malformed.');
        }
        $pathParts = explode('/', trim($urlPath, '/'));
        array_shift($pathParts);

        return implode('/', $pathParts);
    }

    private function hasPublishedDelivery(string $deliveryId): bool
    {
        try {
            $this->deliveryRepository->find($deliveryId);
        } catch (DocumentNotFoundException) {
            return false;
        }

        return true;
    }

    private function processTranslations(Delivery $delivery): void
    {
        $translations = $delivery->getTranslations();
        if (empty($translations)) {
            $this->eventDispatcher->dispatch(
                new DeliveryCreatedEvent($delivery),
            );
            return;
        }

        foreach ($translations as $locale => $translation) {
            $translations[$locale]['state'] = Publication::STATUS_CREATED;
        }
        $delivery
            ->setTranslations($translations)
            ->setIsDisabled(true);
        $this->deliveryRepository->save($delivery);

        foreach ($translations as $locale => $translation) {
            $this->messageBus->dispatch(
                new SynchronousDeliveryLanguageAttachmentMessage(
                    $delivery->getId(),
                    $locale,
                    packageRef: $translation['packageRef'],
                ),
            );
        }
    }

    private function getSupportedLocales(?string $locale, array $configuration): array
    {
        $isHidden = (
            $configuration['metadata']['test']['hidden'] ?? 'false'
        ) === 'true';

        return !$locale || $isHidden ? [] : [$locale];
    }
}
