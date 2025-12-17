<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Delivery\Event\DeliveryCreatedEvent;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\Publication\Model\Publication;
use App\Factory\ExceptionConverterFactory;
use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentFailedMessage;
use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentMessage;
use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentSucceededMessage;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Qti\Exception\QtiPackageExtractorException;
use App\Qti\Extractor\QtiPackageExtractor;
use App\Repository\DeliveryRepository;
use App\TestRunner\ActionProcessor\Exception\ConflictException;
use App\Traits\FilesystemTrait;
use App\Validator\Exception\RequestValidationException;
use App\Validator\Locale\LocaleValidator;
use Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\NoLock;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

readonly class AttachLanguageToDeliveryService
{
    use FilesystemTrait;

    private const BASE_64_FILE_NAME = 'base64PackageContent.txt';

    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private MessageBusInterface $messageBus,
        private LocaleValidator $localeValidator,
        private LoggerInterface $auditPlatformLogger,
        private QtiPackageExtractor $packageExtractor,
        private QtiPackageCompiler $packageCompiler,
        private FilesystemOperator $base64ZipStorage,
        private FilesystemReader $preuploadedPackageStorage,
        private FilesystemReader $dataStoreDeliveryProcessingInput,
        private LockFactory $lockFactory,
        private DeleteDeliveryService $deleteDeliveryService,
        private ExceptionConverterFactory $exceptionConverterFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws DocumentNotFoundException
     */
    public function attach(
        string $deliveryId,
        string $locale,
        ?string $base64PackageContent,
        ?string $packageRef,
    ): Delivery {
        $delivery = $this->deliveryRepository->find($deliveryId);

        try {
            $this->localeValidator->validate($locale);
        } catch (Exception $throwable) {
            throw new RequestValidationException(
                $throwable->getMessage(),
                $throwable->getCode(),
                $throwable,
            );
        }

        if ($delivery->isSupportedLocale($locale)) {
            throw new ConflictException(
                sprintf('Delivery with ID [%s] already contains locale [%s].', $deliveryId, $locale),
            );
        }

        $packagePath = '';
        if ($base64PackageContent) {
            $packagePath = $this->buildPathFor($deliveryId, $locale, self::BASE_64_FILE_NAME);
            $this->base64ZipStorage->write($packagePath, $base64PackageContent);
        }

        $this->messageBus->dispatch(
            new DeliveryLanguageAttachmentMessage(
                $deliveryId,
                $locale,
                $packagePath,
                $packageRef,
            ),
        );

        return $delivery;
    }

    /**
     * @throws Exception
     * @throws FilesystemException
     * @throws QtiPackageExtractorException
     */
    public function handleLocaleAttachment(
        Delivery $delivery,
        string $locale,
        ?string $packagePath,
        ?string $packageRef,
        bool $isSynchronous,
    ): void {
        try {
            $this->updateDeliveryTranslationState($delivery, $locale, Publication::STATUS_STARTED, $isSynchronous);

            $deliveryId = $delivery->getId();
            $this->auditPlatformLogger->info(sprintf('[%s] Attaching locale [%s] to delivery', $deliveryId, $locale));

            $localePath = $locale
                ? $this->buildPathFor(Delivery::LOCALE_FOLDER_NAME, $locale)
                : null;

            $zipPackage = $this->retrievePackage($delivery, $packagePath, $packageRef);
            $extractedPackagePath = $this->packageExtractor->extract(
                $zipPackage,
                $deliveryId,
                $localePath,
            );

            $compilationResult = $this->packageCompiler->compile(
                $deliveryId,
                $extractedPackagePath,
                $delivery->getTenantId(),
                $localePath,
            );

            if (!$compilationResult->isSuccessful()) {
                throw new RuntimeException(sprintf('Failed to attach locale [%s] to the delivery.', $locale));
            }

            $this->auditPlatformLogger->info(
                sprintf('[%s] Locale [%s] was successfully attached to the delivery.', $deliveryId, $locale),
            );

            $this->updateDeliveryTranslationState(
                $delivery,
                $locale,
                Publication::STATUS_SUCCESS,
                $isSynchronous,
            );
        } catch (Exception $throwable) {
            $this->updateDeliveryTranslationState(
                $delivery,
                $locale,
                Publication::STATUS_FAILED,
                $isSynchronous,
                $throwable,
            );

            $this->auditPlatformLogger->error(
                sprintf(
                    '[%s] An error occurred while attaching locale [%s]: %s',
                    $delivery->getId(),
                    $locale,
                    $throwable->getMessage(),
                ),
            );

            throw $throwable;
        }
    }

    private function updateDeliveryTranslationState(
        Delivery $delivery,
        string $locale,
        string $state,
        bool $isSynchronous,
        ?Throwable $exception = null,
    ): void {
        $lock = $isSynchronous
            ? new NoLock()
            : $this->lockFactory->createLock('delivery_update_' . $delivery->getId());
        $lock->acquire(true);

        try {
            $delivery = $this->deliveryRepository->find($delivery->getId());
            $translations = $delivery->getTranslations();

            $translations[$locale]['state'] = $state;
            $translations[$locale]['message'] = $exception?->getMessage();
            $delivery->setTranslations($translations);

            if ($state === Publication::STATUS_SUCCESS) {
                $delivery->setSupportedLocales(array_merge($delivery->getSupportedLocales(), [$locale]));
                $this->enableDeliveryIfAllLocalesHaveBeenAttached($delivery, $translations);
            }

            $this->deliveryRepository->save($delivery);
        } finally {
            $lock->release();
        }
        if (!$delivery->getIsDisabled()) {
            $this->messageBus->dispatch(
                new DeliveryLanguageAttachmentSucceededMessage(
                    $delivery->getId(),
                    $delivery->getTenantId(),
                    $delivery->getConfiguration(),
                ),
            );
            $this->eventDispatcher->dispatch(
                new DeliveryCreatedEvent($delivery),
            );
        }

        if ($state === Publication::STATUS_FAILED) {
            $this->handleFailedState($delivery, $exception);
        }
    }

    private function enableDeliveryIfAllLocalesHaveBeenAttached(Delivery $delivery, array $translations): void
    {
        $states = array_unique(array_column($translations, 'state'));

        if (count($states) === 1) {
            $this->auditPlatformLogger->info(
                sprintf(
                    'Successfully attached all locales to delivery [%s], sending success event.',
                    $delivery->getId(),
                ),
            );

            $delivery->setIsDisabled(false);
        }
    }

    private function handleFailedState(Delivery $delivery, Throwable $exception): void
    {
        $errors = [
            $this->exceptionConverterFactory->convert($exception),
        ];

        $this->auditPlatformLogger->error(
            sprintf(
                'Locale attachment for delivery [%s] failed: %s',
                $delivery->getId(),
                $exception->getMessage(),
            ),
        );

        try {
            $this->deleteDeliveryService->hardDelete($delivery);
        } catch (Exception $exception) {
            $errors[] = $this->exceptionConverterFactory->convert($exception);
        }

        $this->messageBus->dispatch(
            new DeliveryLanguageAttachmentFailedMessage(
                $delivery->getId(),
                $delivery->getTenantId(),
                $delivery->getConfiguration(),
                $delivery->getTranslations(),
                $errors,
            ),
        );
    }


    /**
     * @throws FilesystemException
     * @throws RuntimeException
     */
    private function retrievePackage(
        Delivery $delivery,
        ?string $packagePath,
        ?string $packageRef,
    ): string {
        if ($packagePath) {
            $this->auditPlatformLogger->debug(
                sprintf('[%s] Retrieving package from path: %s', $delivery->getId(), $packagePath),
            );

            $decodedContent = base64_decode($this->base64ZipStorage->read($packagePath));
            if ($decodedContent === false) {
                throw new RequestValidationException(
                    sprintf(
                        'Failed to decode base64 package content from path: [%s]',
                        $packagePath,
                    ),
                );
            }

            return $decodedContent;
        }

        if ($packageRef) {
            $this->auditPlatformLogger->debug(
                sprintf('[%s] Retrieving package from ref: %s', $delivery->getId(), $packageRef),
            );
            if (is_file($packageRef)) {
                $fileContent = @file_get_contents($packageRef);
            } else {
                $configuration = $delivery->getConfiguration();

                if ($configuration['metadata']['portalImport'] ?? false) {
                    return $this->dataStoreDeliveryProcessingInput->read(
                        $packageRef,
                    );
                }

                return $this->preuploadedPackageStorage->read(
                    $this->parseObjectKey($packageRef),
                );
            }

            if ($fileContent === false) {
                throw new RequestValidationException(
                    sprintf('Failed to retrieve package from reference: [%s]', $packageRef),
                );
            }

            return $fileContent;
        }

        throw new RequestValidationException('Neither package path nor package reference was provided.');
    }

    private function parseObjectKey(string $packageRef): string
    {
        $urlPath = parse_url($packageRef, PHP_URL_PATH);
        if (empty($urlPath)) {
            throw new RequestValidationException('Provided packageRef parameter is malformed.');
        }

        $pathParts = explode('/', trim($urlPath, '/'));
        array_shift($pathParts);

        return implode('/', $pathParts);
    }
}
