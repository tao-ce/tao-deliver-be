<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\EventSubscriber;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DeliveryExecution\DeliveryExecutionFinishedMessage;
use App\Messenger\Message\ResultExtractionMessage;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\TestRunner\Event\Control\ControlType;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use App\TestRunner\Event\ProctoredDeliveryExecutionInitializedEvent;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Event\TestSessionResumeEvent;
use App\TestRunner\Event\TestSessionStartEvent;
use App\TestRunner\Service\InteractionMessageService;
use App\TestRunner\Service\TestSessionShutdownService;
use Exception;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TestRunnerEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private LtiCustomSettings $ltiCustomSettings,
        private DataStoreSenderInterface $dataStoreSender,
        private InteractionMessageService $interactionMessageService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TestSessionStartEvent::class => 'onTestSessionStart',
            TestSessionResumeEvent::class => 'onTestSessionResume',
            TestSessionInteractionEvent::class => 'onTestSessionInteraction',
            TestSessionEndEvent::class => 'onTestSessionEnd',
            ProctoredDeliveryExecutionInitializedEvent::class => 'onProctoringAuthorizationRequestSend',
        ];
    }

    public function onTestSessionStart(TestSessionStartEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            new DeliveryExecutionControlEvent(
                $event->getDeliveryExecution(),
                ControlType::START,
            ),
        );
    }

    public function onTestSessionResume(TestSessionResumeEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            new DeliveryExecutionControlEvent(
                $event->getDeliveryExecution(),
                ControlType::RESUME,
            ),
        );
    }
    public function onTestSessionInteraction(TestSessionInteractionEvent $event): void
    {
        if (
            empty($event->getDeliveryExecution()->getLtiLaunchParameters()['is_context_inherited'])
            && !$this->ltiCustomSettings->isMonitoringEnabled($event->getDeliveryExecution()->getLtiLaunchParameters())
        ) {
            return;
        }

        $deliveryExecution = $event->getDeliveryExecution();
        $testSession = $event->getTestSession();
        $map = $event->getTestMap();


        $this->interactionMessageService->createAndPublishInteractionMessage(
            $deliveryExecution,
            $event->getTriggeredBy(),
            $testSession,
            $map,
        );
    }

    public function onProctoringAuthorizationRequestSend(ProctoredDeliveryExecutionInitializedEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution();

        if ($deliveryExecution->getStatus() !== DeliveryExecution::STATUS_INITIAL) {
            $this->logger->info(
                sprintf(
                    '[%s] Skip Published interaction message, triggered by %s: incorrect status',
                    $deliveryExecution->getId(),
                    $event->getTriggeredBy(),
                ),
            );

            return;
        }

        if (empty($deliveryExecution->getLtiLaunchParameters()['context_id'])) {
            throw new LtiException(
                sprintf(
                    '[%s] Context::id claim is required for proctoring communication',
                    $deliveryExecution->getId(),
                ),
            );
        }

        $this->interactionMessageService->createAndPublishInteractionMessage(
            $deliveryExecution,
            $event->getTriggeredBy(),
        );
    }

    public function onTestSessionEnd(TestSessionEndEvent $event): void
    {
        $this->dataStoreSender->send($event->getDeliveryExecution());
        $this->dispatchDeliveryExecutionSubmissionEvent($event);
        $this->dispatchResultExtractionMessage($event);
    }

    private function dispatchDeliveryExecutionSubmissionEvent(TestSessionEndEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution();

        if (!DeliveryExecutionStatus::STATUS_CLOSED->equals($deliveryExecution->getStatus())) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new DeliveryExecutionControlEvent(
                $deliveryExecution,
                ControlType::SUBMISSION,
                deliveryExecutionActorRole: $event->getTriggeredBy() === TestSessionShutdownService::class
                    ? DeliveryExecutionActorRole::ROLE_SYSTEM
                    : DeliveryExecutionActorRole::ROLE_TEST_TAKER,
            ),
        );
    }

    private function dispatchResultExtractionMessage(TestSessionEndEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution()->clone();
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        try {
            $message = new ResultExtractionMessage(
                $this->uuidGenerator->generate(),
                $deliveryExecution->getId(),
            );

            $this->messageBus->dispatch($message);

            $this->logger->debug(
                sprintf(
                    '[%s] Published result extraction message, triggered by %s',
                    $deliveryExecution->getOriginalId(),
                    $event->getTriggeredBy(),
                ),
            );

            $this->messageBus->dispatch(DeliveryExecutionFinishedMessage::createFromEvent($event));
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '[%s] Failed to publish result extraction message: %s',
                    $deliveryExecution->getOriginalId(),
                    $exception->getMessage(),
                ),
                compact('exception'),
            );
        }
    }
}
