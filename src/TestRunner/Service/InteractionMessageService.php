<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Helper\PositionsHelper;
use App\Messenger\Message\InteractionMessage;
use App\Messenger\Stamp\MetadataStamp;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Generator\TestMapGenerator;
use Carbon\Carbon;
use Exception;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiDuration;
use qtism\runtime\tests\AssessmentTestSession;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class InteractionMessageService
{
    private const DEFAULT_CONTEXT_ID = 'default-context-id';

    public function __construct(
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private RequestStack $requestStack,
        private TestMapGenerator $testMapGenerator,
    ) {
    }

    public function createAndPublishInteractionMessage(
        DeliveryExecution $deliveryExecution,
        string $triggeredBy,
        ?AssessmentTestSession $testSession = null,
        ?array $map = null,
        ?string $status = null,
    ) {
        $message = $this->createInteractionMessage($deliveryExecution, $testSession, $map, $status);
        $this->publishInteractionMessage(
            $message,
            $triggeredBy,
            $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? self::DEFAULT_CONTEXT_ID,
        );
    }

    private function publishInteractionMessage(
        InteractionMessage $message,
        string $triggeredBy,
        string $contextId = self::DEFAULT_CONTEXT_ID,
    ): void {
        try {
            $this->messageBus->dispatch(
                $message,
                [
                    new MetadataStamp($contextId),
                ],
            );

            $this->logger->info(
                sprintf(
                    '[%s] Published interaction message, triggered by %s',
                    $message->getDeliveryExecutionId(),
                    $triggeredBy,
                ),
            );
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '[%s] Failed to publish interaction message: %s',
                    $message->getDeliveryExecutionId(),
                    $exception->getMessage(),
                ),
                compact('exception'),
            );
        }
    }

    private function createInteractionMessage(
        DeliveryExecution $deliveryExecution,
        ?AssessmentTestSession $testSession = null,
        ?array $map = null,
        ?string $status = null,
    ): InteractionMessage {
        $testSession ??= $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        try {
            $map ??= $this->testMapGenerator->generate($testSession, $deliveryExecution);
        } catch (RuntimeException) {
            $map = [];
        }

        /**
         * @var ?QtiDuration $testDuration
         */
        $testDuration = $testSession['duration'];
        $durationInSeconds = $testDuration?->getSeconds(true);

        $deliveryExecutionFinishedAt = $deliveryExecution->getFinishedAt()?->getTimestamp()
            ?: Carbon::now()->getTimestamp();

        return new InteractionMessage(
            $deliveryExecution->getResourceLink(),
            $deliveryExecution->getId(),
            $deliveryExecution->getDeliveryId(),
            $deliveryExecution->getTenantId(),
            $deliveryExecutionFinishedAt - $durationInSeconds,
            $durationInSeconds ?? 0,
            $this->requestStack->getCurrentRequest()?->getClientIp(),
            PositionsHelper::getPositionData($testSession),
            $this->getProgressPercentage($map),
            $this->deliveryExecutionPropertyService->getTestTitle($deliveryExecution),
            $map['stats']['questions'] ?? 0,
            $map['stats']['questionsViewed'] ?? 0,
            $map['stats']['answered'] ?? 0,
            $map['stats']['flagged'] ?? 0,
            $map['stats']['viewed'] ?? 0,
            $deliveryExecutionFinishedAt,
            $deliveryExecution->getLtiLaunchParameters()['user_name'] ?? null,
            $status ?? $deliveryExecution->getStatus(),
            PositionsHelper::getPositionDetails($testSession),
            $deliveryExecution->getExtraStateData()->getExternalTimerDefinition() === null ? null : true,
            $deliveryExecution->getLtiLaunchParameters()['battery_id'] ?? null,
            $deliveryExecution->getLtiLaunchParameters()['battery_name'] ?? null,
            $deliveryExecution->getUserSelectedLocaleForMultiLanguage(),
        );
    }

    private function getProgressPercentage(array $map): ?float
    {
        if (!isset($map['stats']['answered'], $map['stats']['questions'])) {
            return .0;
        }

        if ($map['stats']['questions'] <= 0) {
            return null;
        }

        return ($map['stats']['answered'] / $map['stats']['questions']) * 100;
    }
}
