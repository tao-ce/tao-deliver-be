<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\DeliveryExecutionUIEventMessage;
use App\Validator\DeliveryExecution\LogActionEventsProcessorValidator;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Carbon\Carbon;

class LogActionProcessor extends AbstractActionProcessor
{
    private const CHUNK_SIZE = 10;
    private const ACTION_NAME = 'ui-log';
    protected const AVAILABLE_STATUSES = [
        DeliveryExecution::STATUS_INTERACTING,
        DeliveryExecution::STATUS_CLOSED,
    ];

    public function __construct(
        private readonly LogActionEventsProcessorValidator $validator,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $eventsData = $actionParameters['parameters'];
        $events = $this->validator->getValidateRequestData($eventsData);

        $now = Carbon::now()->getTimestampMs();
        $chunks = array_chunk($events['events'], self::CHUNK_SIZE);

        foreach ($chunks as $chunkNumber => $eventChunk) {
            $outputEvents = array_map(static function ($event) use ($now) {
                $event['timestamp'] = $event['metadata']['timeStamp'] ?? $now;
                $event['itemId'] = $event['itemIdentifier'] ?? null;
                $event['responseId'] = $event['responseIdentifier'] ?? null;

                unset($event['itemIdentifier']);
                unset($event['responseIdentifier']);

                return $event;
            }, $eventChunk);
            try {
                $this->messageBus->dispatch(
                    new DeliveryExecutionUIEventMessage($deliveryExecution, $outputEvents),
                );
            } catch (Exception $exception) {
                $this->auditDeliveryExecutionLogger->critical(
                    sprintf(
                        '[%s] failed to dispatch ui-log events chunk %s from %s - %s',
                        $deliveryExecution->getId(),
                        $chunkNumber + 1,
                        count($chunks),
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            }
        }

        return $this->getActionProcessorResponse($actionParameters, []);
    }
}
