<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Lti\Proctoring\AcsActionProcessor\AcsActionProcessorInterface;
use DateTimeInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use JsonException;
use Carbon\Carbon;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\DeliveryExecutionDeleter;
use App\Repository\DeliveryExecutionRepository;
use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\Service\InteractionMessageService;

/**
 * @package App\Action\DeliveryExecution
 */
readonly class DeleteDeliveryExecutionAction
{
    public function __construct(
        private DeliveryExecutionRepository $deliveryExecutionRepository,
        private DeliveryExecutionDeleter $deliveryExecutionDeleter,
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private InteractionMessageService $interactionMessageService,
    ) {
    }

    public function __invoke(string $id, string $tenantId, Request $request): Response
    {
        $deliveryExecution = $this->deliveryExecutionRepository->find($id);

        if ($deliveryExecution->getTenantId() !== $tenantId) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action');
        }

        $this->deliveryExecutionDeleter->delete($deliveryExecution);
        $this->processAcsAction($deliveryExecution, $request->getContent());

        $this->interactionMessageService->createAndPublishInteractionMessage(
            deliveryExecution: $deliveryExecution,
            triggeredBy: self::class,
            status: DeliveryExecution::STATUS_DELETED,
        );

        return new Response(null, Response::HTTP_ACCEPTED);
    }

    public function processAcsAction(DeliveryExecution $deliveryExecution, string $extra): void
    {
        try {
            $extra = json_decode($extra, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // no need to follow this up, as the primary goal is to delete the delivery execution
            $this->logger->notice(
                'Failed process acs log with extract metadata for delete process',
                [
                    'extra' => $extra,
                ],
            );
        }
        if (!isset($extra['acsLog'])) {
            return;
        }

        $acsControlData = $extra['acsLog'];
        try {
            $incidentTime = !empty($acsControlData['incident_time'])
                ? Carbon::parse($acsControlData['incident_time'])
                : Carbon::now();
        } catch (\Exception $e) {
            $this->logger->notice(
                'Failed to parse incident_time, using current time',
                [
                    'incident_time' => $acsControlData['incident_time'] ?? null,
                    'error' => $e->getMessage(),
                ],
            );
            $incidentTime = Carbon::now();
        }
        $acsControl = [
            'userIdentifier' => $deliveryExecution->getUserId(),
            'resourceLink' => [
                'identifier' => $deliveryExecution->getResourceLink()->getIdentifier(),
            ],
            'action' => 'reset',
            'attemptNumber' => $deliveryExecution->getAttempt(),
            'incidentTime' => $incidentTime->format(DateTimeInterface::ATOM),
            'reasonCode' => $acsControlData['reason_code'] ?? '999',
            'reasonMessage' => $acsControlData['reason_msg'] ?? '',
        ];

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $currentAssessmentItemRef = $testSession->getCurrentAssessmentItemRef();
        $itemId = null;
        if ($currentAssessmentItemRef !== false) {
            $itemId = $currentAssessmentItemRef->getIdentifier();
        }

        $this->messageBus->dispatch(
            new DeliveryExecutionAcsLogMessage(
                $deliveryExecution->getId(),
                $itemId,
                AcsActionProcessorInterface::STATUSES_MAP[$deliveryExecution->getStatus()]
                ?? AcsControlResultInterface::STATUS_NONE,
                $acsControl,
            ),
        );
    }
}
