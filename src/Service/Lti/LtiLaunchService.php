<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Lti;

use App\Domain\Battery\Generator\BatteryDistributionKeyGenerator;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsResumeEmulation\Event\ProctoredAssessmentForceResumed;
use App\Lti\Response\LtiForwardResponse;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Repository\DeliveryRepository;
use App\Service\ApplicationInfoService;
use App\Service\Battery\BatteryService;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\Lti\Dto\StartProctoringRequestContext;
use App\TestRunner\Event\DeliveryExecutionLaunchEvent;
use App\TestRunner\Event\ProctoredDeliveryExecutionInitializedEvent;
use App\TestRunner\Service\TestSessionInitiator;
use App\TestRunner\Service\TestSessionNavigator;
use App\Validator\Exception\RequestValidationException;
use Carbon\Carbon;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\EnvironmentManagementClientBundle\Http\ResponseHelper;
use OAT\Library\EnvironmentManagementClient\Exception\ConfigurationNotFoundException;
use OAT\Library\EnvironmentManagementClient\Http\AuthorizationDetailsMarkerInterface;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class LtiLaunchService
{
    public function __construct(
        private BatteryService $batteryService,
        private DeliveryRepository $deliveryRepository,
        private FilesystemReader $qtiCompiledDeliveriesStorage,
        private TestSessionInitiator $testSessionInitiator,
        private TestSessionNavigator $testSessionNavigator,
        private ParameterBagInterface $parameterBag,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
        private LoggerInterface $auditDeliveryExecutionLogger,
        private LtiCustomSettings $ltiCustomSettings,
        private LtiTokenResolver $ltiTokenResolver,
        private LtiProctoringService $ltiProctoringService,
        private ResponseHelper $responseHelper,
        private ApplicationInfoService $applicationInfoService,
        private EventDispatcherInterface $eventDispatcher,
        private LtiExtraTimeHandler $ltiExtraTimeHandler,
        private ConfigurationRepositoryInterface $configurationRepository,
    ) {
    }

    public function launch(
        string $deliveryId,
        array $parameters,
        LtiMessagePayloadInterface $lti1p3MessagePayload,
    ): Response {
        $tenantId = $lti1p3MessagePayload->getToken()->getClaims()->get('tenant_id');
        try {
            $delivery = $this->deliveryRepository->find($deliveryId);
        } catch (DocumentNotFoundException $exception) {
            if (
                !$this->ltiCustomSettings->isReviewModeEnabled($parameters)
                || !$this->qtiCompiledDeliveriesStorage->directoryExists("$tenantId/$deliveryId")
            ) {
                throw new NotFoundHttpException(
                    sprintf('[IRRECOVERABLE] Delivery id %s not found', $deliveryId),
                    $exception,
                );
            }
            $delivery = new Delivery(
                $deliveryId,
                $tenantId,
                Carbon::now(),
                sprintf(
                    '%s/%s/%s',
                    $tenantId,
                    $deliveryId,
                    QtiPackageCompiler::COMPACT_TEST_FILE_NAME,
                ),
                ['unlisted' => true],
            );
        }

        $this->validateTenant($delivery, $lti1p3MessagePayload);
        $deliveryExecution = $this->deliveryExecutionService->getDeliveryExecution($delivery, $parameters);
        $this->validateDates($delivery, $deliveryExecution);

        if (!isset($parameters['result_id'])) {
            $parameters['result_id'] = $deliveryExecution->getId();
        }

        return $this->requireAuthorization(
            new StartProctoringRequestContext($lti1p3MessagePayload, $deliveryExecution, $delivery, $parameters),
        );
    }

    public function launchTest(
        DeliveryExecution $deliveryExecution,
        array $parameters,
        ?Delivery $delivery = null,
        bool $redirect = true,
    ): Response {
        $commonLocales = $this->getCommonLocales($delivery, $parameters);
        $isInitialLaunch = $delivery !== null;
        $delivery ??= $this->deliveryRepository->find($deliveryExecution->getDeliveryId());
        $deliveryExecution = $this->updateDeliveryExecutionLocaleData($delivery, $deliveryExecution, $commonLocales);

        $this->ltiExtraTimeHandler->addExtraTime($deliveryExecution);

        if ($deliveryExecution->isStateFinal() && $this->ltiCustomSettings->isAutoReviewModeEnabled($parameters)) {
            $deliveryExecution = $this->deliveryExecutionService->createDeliveryExecution(
                $delivery,
                DeliveryExecution::REVIEW_MODE_PREFIX
                . DeliveryExecution::DOCUMENT_KEY_DELIMITER
                . $deliveryExecution->getId(),
                $parameters,
                $deliveryExecution->getQtiCompactTestFilePath(),
                $deliveryExecution->getLocale(),
            );
        }

        $supportedLocales = [];
        $isTestSessionReInitializationNeeded = $this->isTestSessionReInitializationNeeded(
            $deliveryExecution,
            $parameters,
        );

        if (!$isTestSessionReInitializationNeeded && $isInitialLaunch) {
            $supportedLocales = empty($parameters['battery_id'])
                ? $delivery->getSupportedLocales()
                : $commonLocales;
        }

        // This condition must match the condition upon which the locale selector is presented to the test taker
        if (count($supportedLocales) < 2) {
            $this->testSessionInitiator->init(
                $deliveryExecution,
                $isTestSessionReInitializationNeeded,
            );
        }

        if (
            !$this->ltiCustomSettings->isReviewModeEnabled($parameters)
            && $this->ltiCustomSettings->isItemLaunchEnabled($parameters)
            && !$this->testSessionNavigator->navigateToItemRef(
                $deliveryExecution,
                $this->ltiCustomSettings->getItemLaunch($parameters),
            )
        ) {
            throw new RequestValidationException(
                sprintf(
                    '[IRRECOVERABLE] Unable to find the item identifier %s to reach provided in LTI custom claims',
                    $this->ltiCustomSettings->getItemLaunch($parameters),
                ),
            );
        }

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test was initialized',
                $deliveryExecution->getId(),
            ),
        );

        if ($parameters['result_id'] ?? false) {
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - result identifier: [%s] was provided for current test',
                    $deliveryExecution->getId(),
                    $parameters['result_id'],
                ),
            );
        }

        if ($parameters['client_id'] ?? false) {
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - client identifier: [%s] was provided for current test',
                    $deliveryExecution->getId(),
                    $parameters['client_id'],
                ),
            );
        }

        if (!isset($parameters['client_id'])) {
            throw new AuthenticationException('Client id is missing');
        }

        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        if ($isTestSessionReInitializationNeeded && $this->ltiCustomSettings->isMonitoringEnabled($parameters)) {
            $this->eventDispatcher->dispatch(new ProctoredAssessmentForceResumed($deliveryExecution));
        }

        $this->eventDispatcher->dispatch(new DeliveryExecutionLaunchEvent($deliveryExecution));

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker was provided JWT Token  %s',
                $deliveryExecution->getId(),
                $parameters['client_id'],
            ),
        );

        $deliverFrontendUrl = $this->parameterBag->get('deliver.frontend.url');

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker will be redirected to the graphical user interface (FE): %s ',
                $deliveryExecution->getId(),
                $deliverFrontendUrl,
            ),
        );

        if ($deliveryExecution->isReview()) {
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - delivery has been launched in review mode',
                    $deliveryExecution->getId(),
                ),
            );
        }

        return $this->lti1p3Redirection(
            $deliveryExecution,
            $parameters,
            $deliverFrontendUrl,
            $isInitialLaunch && $redirect,
        );
    }

    public function requireAuthorization(
        StartProctoringRequestContext $startProctoringRequestContext,
        bool $redirect = true,
    ): Response {
        $deliveryExecution = $startProctoringRequestContext->deliveryExecution;
        $delivery = $startProctoringRequestContext->delivery;
        $parameters = $startProctoringRequestContext->launchParameters;
        if (!$redirect || !$this->ltiCustomSettings->isMonitoringEnabled($parameters)) {
            return $this->launchTest($deliveryExecution, $parameters, $delivery, $redirect);
        }

        $proctoringLink = $this->ltiProctoringService->getStartProctoringRequestUrl($startProctoringRequestContext);
        if (!$proctoringLink) {
            return $this->launchTest($deliveryExecution, $parameters, $delivery, $redirect);
        }

        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        $this->eventDispatcher->dispatch(
            new ProctoredDeliveryExecutionInitializedEvent(
                self::class,
                $deliveryExecution,
            ),
        );

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - redirected to start proctoring: %s',
                $deliveryExecution->getId(),
                $proctoringLink,
            ),
        );

        return $redirect
            ? new RedirectResponse($proctoringLink)
            : new LtiForwardResponse($proctoringLink);
    }

    private function lti1p3Redirection(
        DeliveryExecution $deliveryExecution,
        array $parameters,
        string $deliverFrontendUrl,
        bool $redirect = true,
    ): Response {
        $deliveryExecutionId = $deliveryExecution->getId();

        if (!empty($parameters['battery_id'])) {
            $refreshTokenId = BatteryDistributionKeyGenerator::generateBatteryDistributionKey(
                $parameters['battery_id'],
                $deliveryExecution->getLtiLaunchParameters()['user_id'],
                $deliveryExecution->getAttemptId(),
            );
        } else {
            $refreshTokenId = $this->ltiCustomSettings->isAutoReviewModeEnabled($parameters)
                ? $deliveryExecution->getOriginalId()
                : $deliveryExecution->getId();
        }

        if ($this->ltiCustomSettings->isItemLaunchEnabled()) {
            $refreshTokenId .= $this->ltiCustomSettings->getItemLaunch();
        }

        $deliveryExecutionUrl = sprintf(
            '%s?%s',
            $deliverFrontendUrl,
            http_build_query([
                'deliveryExecutionId' => rawurlencode($deliveryExecutionId),
                'refreshTokenId' => rawurlencode($refreshTokenId),
            ]),
        );

        if (!$redirect) {
            return new LtiForwardResponse($deliveryExecutionUrl);
        }

        $response = $this->responseHelper->withAuthorizationDetailsMarker(
            new Response(),
            $parameters['client_id'],
            $refreshTokenId,
            cookieDomain: $this->applicationInfoService->getCookieDomain(),
            ltiToken: $this->resolveLtiToken($deliveryExecution, $parameters),
            mode: $this->getAuthMode($deliveryExecution),
            storagePrefix: 'tao-store-jwt.tao-deliver.',
        );

        $redirectResponse = new RedirectResponse($deliveryExecutionUrl);
        $redirectResponse->headers->add(
            $response->headers->allPreserveCase(),
        );
        return $redirectResponse;
    }

    private function getAuthMode(DeliveryExecution $deliveryExecution): string
    {
        $mode = null;
        try {
            $mode = $this->configurationRepository
                ->find($deliveryExecution->getTenantId(), 'AUTH_MODE')
                ?->getStringValue();
        } catch (ConfigurationNotFoundException) {
        }
        return $mode ?: AuthorizationDetailsMarkerInterface::MODE_COOKIE;
    }

    /**
     * @throws ConflictHttpException
     */
    private function validateDates(Delivery $delivery, DeliveryExecution $deliveryExecution): void
    {
        if ($deliveryExecution->isReview() && $deliveryExecution->getUserId()) {
            return;
        }

        $configuration = $delivery->getConfiguration();
        $now = Carbon::now();

        if (
            isset($configuration['expiryDate']) && $configuration['expiryDate'] > 0 &&
            $now->greaterThan(Carbon::createFromTimestamp($configuration['expiryDate']))
        ) {
            throw new ConflictHttpException(
                sprintf(
                    '[IRRECOVERABLE] Delivery ID %s can not be launched due to start and end dates configuration',
                    $delivery->getId(),
                ),
            );
        }

        if (
            isset($configuration['availabilityDate']) && $configuration['availabilityDate'] > 0 &&
            $now->lessThan(Carbon::createFromTimestamp($configuration['availabilityDate']))
        ) {
            throw new ConflictHttpException(
                sprintf(
                    '[RECOVERABLE] Delivery ID %s can not be launched due to start and end dates configuration',
                    $delivery->getId(),
                ),
            );
        }
    }

    /**
     * @throws AccessDeniedHttpException
     */
    private function validateTenant(Delivery $delivery, LtiMessagePayloadInterface $lti1p3MessagePayload): void
    {
        $token = $lti1p3MessagePayload->getToken();
        $tenantId = $token->getClaims()->get('tenant_id');

        if ($tenantId && $tenantId !== $delivery->getTenantId()) {
            throw new AccessDeniedHttpException("Tenant id is not valid");
        }
    }

    private function isTestSessionReInitializationNeeded(DeliveryExecution $deliveryExecution, array $parameters): bool
    {
        return $this->ltiCustomSettings->isForceResumeModeEnabled($parameters)
            && $deliveryExecution->getStatus() !== DeliveryExecution::STATUS_INITIAL
            && $deliveryExecution->getStatus() !== DeliveryExecution::STATUS_INTERACTING;
    }

    public function resolveLtiToken(DeliveryExecution $deliveryExecution, array $launchParameters): ?string
    {
        try {
            return $launchParameters['id_token'] ?? $this->ltiTokenResolver->resolve($deliveryExecution)->toString();
        } catch (RuntimeException) {
            return null;
        }
    }

    private function getCommonLocales(?Delivery $delivery, array $parameters): array
    {
        if (empty($parameters['battery_id'])) {
            return $delivery?->getSupportedLocales() ?? [];
        }

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - Getting common locales for the Battery',
                $parameters['battery_id'],
            ),
        );

        $commonLocales = $this->batteryService->getCommonLocales(
            $this->batteryService->findBatteryOrFail($parameters['battery_id']),
        );

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - Common locales: %s',
                $parameters['battery_id'],
                json_encode($commonLocales),
            ),
        );

        return $commonLocales;
    }

    private function updateDeliveryExecutionLocaleData(
        Delivery $delivery,
        DeliveryExecution $deliveryExecution,
        array $commonLocales,
    ): DeliveryExecution {
        if (!$deliveryExecution->getLocale() && $deliveryExecution->isStateInitial() && count($commonLocales) == 1) {
            $this->deliveryExecutionService->setLocaleForDeliveryExecution(
                $delivery,
                $deliveryExecution,
                $commonLocales[0],
            );
        }

        return $deliveryExecution;
    }
}
