<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Logger\ExceptionContextLogger\ExceptionContextLoggerService;
use App\Lti\LtiCustomSettings;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\InitActionProcessor;
use App\TestRunner\ActionProcessor\PauseActionProcessor;
use App\TestRunner\ActionProcessor\Registry\ActionProcessorRegistry;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Service\ActionIdProvider;
use App\TestRunner\Service\BatteryDistributionService;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\RealTimeService;
use App\Validator\Exception\RequestValidationException;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

readonly class ActionProcessorHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private BatteryNavigationService $batteryNavigationService,
        private BatteryDistributionService $batteryDistributionService,
        private ActionProcessorRegistry $actionProcessorRegistry,
        private RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private EventDispatcherInterface $eventDispatcher,
        private LtiCustomSettings $ltiCustomSettings,
        private ExceptionContextLoggerService $exceptionContextLoggerService,
        private ActionIdProvider $actionIdProvider,
        private RealTimeService $realTimeService,
        private RequestStack $requestStack,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function handle(string $deliveryExecutionId, array $actions): array
    {
        $responses = [];
        $isActionProcessingFailed = false;

        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
        $isDeliveryExecutionActive = !$deliveryExecution->getFinishedAt();

        foreach ($actions as $actionParameters) {
            try {
                if ($isActionProcessingFailed) {
                    throw new CantPerformActionException('Action processing has been terminated', 0);
                }
                if ($deliveryExecution->isDeleted()) {
                    throw CantPerformActionException::becauseTestSessionReset(
                        $actionParameters['name'],
                    );
                }

                $cloneDeliveryExecution = clone $deliveryExecution;

                $actionProcessor = $this->actionProcessorRegistry->get($actionParameters['name']);
                $this->actionIdProvider->set($actionParameters['id']);
                $validationResult = $this->validateDeliveryExecution(
                    $deliveryExecution,
                    $actionProcessor,
                    $actionParameters,
                );
                if ($validationResult) {
                    $responses[] = $validationResult;
                    break;
                }

                $responses[] = $actionProcessor->process($deliveryExecution, $actionParameters);

                if ($isDeliveryExecutionActive && $deliveryExecution->getFinishedAt() !== null) {
                    $isDeliveryExecutionActive = false;
                    $this->finalizeDeliveryExecution($deliveryExecution);
                }
            } catch (RequestValidationException $validationException) {
                throw new RequestValidationException(
                    $validationException->getMessage(),
                    $validationException->getCode(),
                    $validationException,
                );
            } catch (Exception $exception) {
                if (!$exception instanceof CantPerformActionException) {
                    $this->exceptionContextLoggerService->logException($exception);
                }
                $isActionProcessingFailed = true;
                $deliveryExecution = $cloneDeliveryExecution;
                $responses[] = $this->getFailedActionProcessorResponse($actionParameters, $exception);
            } finally {
                $this->actionIdProvider->set(null);
            }
        }

        if ($deliveryExecution->getFinishedAt() === null) {
            $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);
        }

        return $responses;
    }

    private function getFailedActionProcessorResponse(array $actionParameters, Throwable $exception): array
    {
        return [
            'success'      => false,
            'name'         => $actionParameters['name'],
            'id'           => $actionParameters['id'],
            'errorCode'    => $exception->getCode(),
            'errorMessage' => $exception->getMessage(),
            'values'       => [],
            '_exception'   => get_class($exception),
        ];
    }

    private function validateDeliveryExecution(
        DeliveryExecution $deliveryExecution,
        ActionProcessorInterface $action,
        array $actionParameters,
    ): array {
        if (
            $this->ltiCustomSettings->isDryRunEnabled($deliveryExecution->getLtiLaunchParameters())
            || $deliveryExecution->isReview()
            || $this->ltiCustomSettings->isForceResumeModeEnabled($deliveryExecution->getLtiLaunchParameters())
        ) {
            return [];
        }

        $ipValidationResult = $this->validateRequestIp($deliveryExecution, $action, $actionParameters);
        if ($ipValidationResult) {
            return $ipValidationResult;
        }
        $action->validateAvailability($deliveryExecution->getStatus());
        return [];
    }

    private function validateRequestIp(
        DeliveryExecution $deliveryExecution,
        ActionProcessorInterface $action,
        array $actionParameters,
    ): array {
        $requestIp = $this->requestStack->getCurrentRequest()?->getClientIp();
        if (
            !$deliveryExecution->getUserId()
            || !$requestIp
            || !$deliveryExecution->getRequestIp()
            || $requestIp === $deliveryExecution->getRequestIp()
            || (
                empty($deliveryExecution->getLtiLaunchParameters()['is_context_inherited'])
                && !$this->ltiCustomSettings->isMonitoringEnabled($deliveryExecution->getLtiLaunchParameters())
            )
            || !$this->featureFlagAdapter->isEnabled(
                $deliveryExecution->getTenantId(),
                'PAUSE_SESSION_ON_IP_CHANGE_ENABLED',
            )
        ) {
            $deliveryExecution->setRequestIp($requestIp);
            return [];
        }

        $actionParameters['parameters']['plugin'] = 'ip-change';
        $validationResult = $this->actionProcessorRegistry->get(PauseActionProcessor::ACTION_NAME)->process(
            $deliveryExecution->setRequestIp($requestIp),
            $actionParameters,
        );

        return $this->realTimeService->isEnabled() && $action->getActionName() !== InitActionProcessor::ACTION_NAME
            ? $validationResult
            : [];
    }

    private function finalizeDeliveryExecution(DeliveryExecution $deliveryExecution): void
    {
        if (
            $this->ltiCustomSettings->isDryRunEnabled($deliveryExecution->getLtiLaunchParameters())
            && !$this->ltiCustomSettings->isAutoReviewModeEnabled($deliveryExecution->getLtiLaunchParameters())
        ) {
            $this->deleteDeliveryExecution($deliveryExecution);
            return;
        }
        $this->eventDispatcher->dispatch(
            new TestSessionEndEvent(self::class, $deliveryExecution),
        );
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);
    }

    private function deleteDeliveryExecution(DeliveryExecution $deliveryExecution): void
    {
        $batteryId = $deliveryExecution->getBatteryId();
        if ($batteryId === null) {
            $this->silentlyDeleteDeliveryExecution($deliveryExecution);
            return;
        }
        $batteryDistribution = $this->batteryNavigationService->getBatteryDistribution($deliveryExecution);
        $nextDeliveryExecution = $this->batteryNavigationService->getNextDeliveryExecution(
            $deliveryExecution,
            $batteryDistribution,
        );

        if ($nextDeliveryExecution !== null) {
            return;
        }

        $this->batteryDistributionService->deleteDeliveryExecutionsLinkedToBatteryDistribution(
            $batteryDistribution,
            $deliveryExecution,
        );
    }

    private function silentlyDeleteDeliveryExecution(DeliveryExecution $deliveryExecution): void
    {
        try {
            $this->deliveryExecutionService->deleteDeliveryExecution($deliveryExecution);
        } catch (Exception $exception) {
            $this->logger->warning(
                sprintf(
                    '[%s] Failed to delete delivery execution after completed dry run, with message: %s',
                    $deliveryExecution->getId(),
                    $exception->getMessage(),
                ),
                compact('exception'),
            );
        }
    }
}
