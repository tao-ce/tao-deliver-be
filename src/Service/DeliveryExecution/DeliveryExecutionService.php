<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Repository\DeliveryExecutionAlias\Contract\DeliveryExecutionIdentifierAliasRepositoryInterface;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\TestRunner\ActionProcessor\Exception\ConflictException;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Event\DeliveryExecutionPersistedEvent;
use App\TestRunner\Service\TestSessionInitiator;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use const PHP_SAPI;

class DeliveryExecutionService implements DeliveryExecutionServiceInterface
{
    public const PARAM_ANONYMOUS_USER_ID = 'anonymous_user_id';
    private const LOCK_KEY_PATTERN = 'locks:delivery-execution-update-%s';

    private const CONTEXT_ID = 'context-id';

    /** @var LockInterface[] */
    private array $locks = [];

    public function __construct(
        private readonly DeliveryExecutionRepository $deliveryExecutionRepository,
        private readonly UuidGenerator $uuidGenerator,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DeliveryExecutionDeleter $deliveryExecutionDeleter,
        private readonly LockFactory $optionalLockFactory,
        private readonly DeliveryExecutionIdentifierAliasRepositoryInterface $deliveryExecutionAliasRepository,
        private readonly TestSessionInitiator $testSessionInitiator,
    ) {
    }

    //
    //  @start banch of proxy method to repo
    //     According to clean architecture we shouldn't use repos directly without services
    //       it because in feature probably we will need add additional actions to it that style reduce amount of
    //       places with changes: events, validation etc..

    public function saveDeliveryExecution(DeliveryExecution $deliveryExecutionModel): void
    {
        if ($deliveryExecutionModel->isReview()) {
            return;
        }

        $this->deliveryExecutionRepository->save($deliveryExecutionModel);
        $this->releaseOptionalLock($deliveryExecutionModel->getId());

        if (!$deliveryExecutionModel->isSnapshot()) {
            $this->eventDispatcher->dispatch(
                new DeliveryExecutionPersistedEvent($deliveryExecutionModel),
            );
        }
    }

    public function deleteDeliveryExecution(DeliveryExecution $deliveryExecutionModel): void
    {
        $this->deliveryExecutionRepository->delete($deliveryExecutionModel);
        if ($deliveryExecutionModel->getAliasId()) {
            $this->deliveryExecutionAliasRepository->deleteDeliveryExecutionId($deliveryExecutionModel->getAliasId());
        }
    }

    public function findDeliveryExecution(string $deliveryExecutionId): ?DeliveryExecution
    {
        try {
            $deliveryExecution = $this->findDeliveryExecutionOrFail($deliveryExecutionId);
        } catch (DocumentNotFoundException) {
            $deliveryExecution = null;
        }
        return $deliveryExecution;
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function findDeliveryExecutionOrFail(string $deliveryExecutionId): DeliveryExecution
    {
        /** The lock will be used only with Elasticsearch storage */
        /** Production environments that rely on Bigtable's atomic record mutations will use @see \App\Storage\Lock\NoopLockStore */
        $this->acquireOptionalLock($deliveryExecutionId);
        return $this->deliveryExecutionRepository->find($deliveryExecutionId);
    }

    public function getDeliveryExecution(
        Delivery $delivery,
        array $parameters,
        ?string $locale = null,
    ): DeliveryExecution {
        $isResultIdMissing = empty($parameters['result_id']);
        $isUserIdMissing = empty($parameters['user_id']);

        if (!isset($parameters['context_id'])) {
            $parameters['context_id'] = $delivery->getMetadataPropertyValue(self::CONTEXT_ID) ?: null;

            if (isset($parameters['context_id'])) {
                $parameters['is_context_inherited'] = true;
            }
        }

        $parameters['user_id'] = $isUserIdMissing
            ? $parameters[self::PARAM_ANONYMOUS_USER_ID] ?? 'anonymous-' . $this->uuidGenerator->generateMedium()
            : $parameters['user_id'];

        $deliveryExecutionId = $this->createDeliveryExecutionId(
            $delivery->getId(),
            $delivery->getTenantId(),
            $parameters,
        );

        if ($isResultIdMissing) {
            $parameters['result_id'] = $deliveryExecutionId;
        }

        if ($this->ltiCustomSettings->isReviewModeEnabled($parameters)) {
            $deliveryExecution = $this->getReviewableDeliveryExecution(
                $delivery,
                $isUserIdMissing,
                $deliveryExecutionId,
                $parameters,
                locale: $locale,
            );

            if (!$deliveryExecution) {
                throw new NotFoundHttpException('[IRRECOVERABLE] Not found delivery execution for provided test taker');
            }

            return $deliveryExecution;
        }

        try {
            if ($this->ltiCustomSettings->isDryRunEnabled($parameters)) {
                return $this->createDeliveryExecution($delivery, $deliveryExecutionId, $parameters, $locale);
            }
            if (!$isResultIdMissing || !$isUserIdMissing) {
                $deliveryExecution = $this->findDeliveryExecutionOrFail($deliveryExecutionId);
                $deliveryExecution->setLtiLaunchParameters($parameters);

                if (!$isUserIdMissing) {
                    if ($deliveryExecution->isDeleted()) {
                        return $this->createDeliveryExecutionFromSeed(
                            $delivery,
                            $deliveryExecution,
                            $parameters,
                            $locale,
                        );
                    }

                    if ($this->ltiCustomSettings->getAttemptLimit($parameters) !== null) {
                        return $this->resolveAttemptLimitedLaunch(
                            $delivery,
                            $deliveryExecution,
                            $parameters,
                            $locale,
                        );
                    }

                    if ($this->ltiCustomSettings->isResetEnabled($parameters)) {
                        $this->deliveryExecutionDeleter->deleteRelatedEntities($deliveryExecution);

                        return $this->createDeliveryExecutionFromSeed(
                            $delivery,
                            $deliveryExecution,
                            $parameters,
                            $locale,
                        );
                    }

                    if (!$deliveryExecution->isStateFinal()) {
                        return $deliveryExecution;
                    }

                    if ($this->ltiCustomSettings->isForceResumeModeEnabled($parameters)) {
                        return $deliveryExecution->reopen();
                    }

                    if ($this->ltiCustomSettings->isAutoReviewModeEnabled($parameters)) {
                        return $deliveryExecution;
                    }
                }

                throw new ConflictException('[IRRECOVERABLE] You don’t have any more attempts for this test');
            }
            return $this->createDeliveryExecution($delivery, $deliveryExecutionId, $parameters, $locale);
        } catch (DocumentNotFoundException) {
            if (str_contains($parameters['user_id'], '.')) {
                $fallbackParameters = $parameters;
                $fallbackParameters['user_id'] = str_replace('.', '-', $parameters['user_id']);
                $fallbackDeliveryExecutionId = $this->createDeliveryExecutionId(
                    $delivery->getId(),
                    $delivery->getTenantId(),
                    $fallbackParameters,
                );
                $fallbackDeliveryExecution = $this->findDeliveryExecution($fallbackDeliveryExecutionId);
                if (null !== $fallbackDeliveryExecution) {
                    return $fallbackDeliveryExecution;
                }
            }
            return $this->createDeliveryExecution($delivery, $deliveryExecutionId, $parameters, $locale);
        }
    }

    public function createDeliveryExecutionId(string $deliveryId, string $tenantId, array $parameters): string
    {
        $deliveryExecutionId = $this->extractExplicitDeliveryExecutionId($deliveryId, $parameters);
        if (null !== $deliveryExecutionId) {
            return $deliveryExecutionId;
        }

        $attemptId = $this->ltiCustomSettings->isDryRunEnabled($parameters)
            ? DeliveryExecution::DRY_RUN_ATTEMPT_ID
            : $this->ltiCustomSettings->getAttemptId($parameters);

        // Check if user ID was generated as URL from TAO 3.x
        if (filter_var($parameters['user_id'], FILTER_VALIDATE_URL)) {
            $userId = urlencode($parameters['user_id']);
        } else {
            $userId = $this->replaceForwardSlashesByDash($parameters['user_id']);
        }

        return $this->assembleDeliveryExecutionId($userId, $deliveryId, $attemptId, $tenantId);
    }

    public function createDeliveryExecution(
        Delivery $delivery,
        string $deliveryExecutionId,
        array $parameters,
        ?string $qtiCompactTestFilePath = null,
        ?DeliveryExecutionExtraStateData $extraStateData = null,
        ?string $locale = null,
    ): DeliveryExecution {
        $deliveryExecution = DeliveryExecutionFactory::create(
            $deliveryExecutionId,
            $parameters,
            null,
            $extraStateData,
            closeAt: $this->ltiCustomSettings->getCloseAt($parameters),
            locale: $locale,
        );
        if ($delivery->getMainLocale()) {
            $deliveryExecution->setMainLocale($delivery->getMainLocale());
        }
        if ($delivery->isMultiLanguage()) {
            $deliveryExecution->setMultiLanguage();
        }

        $deliveryExecution->setDeliveryPublicationTime($delivery->getCreatedAt());

        $this->eventDispatcher->dispatch(
            new DeliveryExecutionCreatedEvent($deliveryExecution),
        );

        return $deliveryExecution;
    }

    public function setLocaleForDeliveryExecution(
        ?Delivery $delivery,
        DeliveryExecution $deliveryExecution,
        string $locale,
    ): void {
        if (!$deliveryExecution->isStateInitial()) {
            throw new ConflictException('Locale has already been set and cannot be overridden.');
        }

        if (!$delivery->isSupportedLocale($locale)) {
            throw new ConflictException('Selected locale is not supported by this delivery.');
        }

        if ($delivery->getMainLocale() !== $locale) {
            $deliveryExecution->setLocale($locale);
        }

        $this->testSessionInitiator->init($deliveryExecution);
        $this->deliveryExecutionRepository->save($deliveryExecution);
    }

    public function createDeliveryExecutionFromSeed(
        Delivery $delivery,
        DeliveryExecution $deliveryExecution,
        array $parameters,
        ?string $locale = null,
    ): DeliveryExecution {
        return $this->createDeliveryExecution(
            $delivery,
            $deliveryExecution->getId(),
            $parameters,
            extraStateData: (new DeliveryExecutionExtraStateData())
                ->withInitialStartTimestamp($deliveryExecution->getInitialStartTimestamp())
                ->withAttempt($deliveryExecution->getAttempt()),
            locale: $locale,
        );
    }

    public function assembleDeliveryExecutionId(
        string $userId,
        string $deliveryId,
        ?string $attemptId,
        string $tenantId,
    ): string {
        return implode(
            DeliveryExecution::DOCUMENT_KEY_DELIMITER,
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId ?: DeliveryExecution::ATTEMPT_ID),
                $tenantId,
            ],
        );
    }

    private function extractExplicitDeliveryExecutionId(string $deliveryId, array $parameters): ?string
    {
        $deliveryExecutionId = $this->ltiCustomSettings->getReviewDeliveryExecutionId($parameters);
        if (null === $deliveryExecutionId) {
            return null;
        }

        $deliveryExecutionKey = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($deliveryExecutionId);
        if (null === $deliveryExecutionKey) {
            return null;
        }

        $userIdForCheck = $parameters['user_id'];
        if (!filter_var($userIdForCheck, FILTER_VALIDATE_URL)) {
            $userIdForCheck = $this->replaceForwardSlashesByDash($parameters['user_id']);
        }

        if (
            $deliveryId !== $deliveryExecutionKey->getDeliveryId()
            || $userIdForCheck !== $deliveryExecutionKey->getUserId()
        ) {
            throw new AccessDeniedHttpException(
                'Invalid delivery execution ID, userId or deliveryId did not match',
            );
        }

        return $deliveryExecutionId;
    }

    private function getReviewableDeliveryExecution(
        Delivery $delivery,
        bool $isUserIdMissing,
        string $deliveryExecutionId,
        array $parameters,
        ?string $locale = null,
    ): ?DeliveryExecution {
        if ($isUserIdMissing) {
            $parameters['user_id'] = 'anonymous';
            $parameters['is_anonymous'] = true;
        }
        $reviewDeliveryExecutionId = (
            $delivery->isUnlisted()
                ? DeliveryExecution::UNLISTED_REVIEW_MODE_PREFIX
                : DeliveryExecution::REVIEW_MODE_PREFIX
        ) . DeliveryExecution::DOCUMENT_KEY_DELIMITER
            . $this->createDeliveryExecutionId($delivery->getId(), $delivery->getTenantId(), $parameters);

        if (!$isUserIdMissing) {
            try {
                $this->findDeliveryExecutionOrFail($deliveryExecutionId);
            } catch (DocumentNotFoundException) {
                return null;
            }
        }

        return $this->createDeliveryExecution($delivery, $reviewDeliveryExecutionId, $parameters, $locale);
    }

    private function resolveAttemptLimitedLaunch(
        Delivery $delivery,
        DeliveryExecution $deliveryExecution,
        array $parameters,
        ?string $locale = null,
    ): DeliveryExecution {
        if (
            '0' === $this->ltiCustomSettings->getAttemptLimit($parameters)
            && $deliveryExecution->isStateFinal()
        ) {
            $this->deliveryExecutionDeleter->deleteRelatedEntities($deliveryExecution);

            return $this->createDeliveryExecutionFromSeed($delivery, $deliveryExecution, $parameters, $locale);
        }

        return $deliveryExecution;
    }

    private function replaceForwardSlashesByDash(string $target): string
    {
        return str_replace('/', '-', $target);
    }

    private function acquireOptionalLock(string $key): void
    {
        if (PHP_SAPI === 'cli' || isset($this->locks[$key])) {
            return;
        }

        $this->locks[$key] = $this->optionalLockFactory->createLock(sprintf(self::LOCK_KEY_PATTERN, $key), null);
        $this->locks[$key]->acquire(true);
    }

    private function releaseOptionalLock(string $key): void
    {
        unset($this->locks[$key]);
    }
}
