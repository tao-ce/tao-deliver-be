<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\Tenant\Model\DeliverProvisionedEventsSettingsRepositoryInterface;
use App\Messenger\Message\DeliveryExecution\ExecutionControlMessage;
use App\Messenger\Message\DeliveryExecution\ExecutionLogMessage;
use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Make the assessment log being logged configurable on the tenant level
 */
class AssessmentLogMessageFilterMiddleware implements MiddlewareInterface
{
    public const ANY_EVENT_MASK = '*';

    public function __construct(
        private DeliverProvisionedEventsSettingsRepositoryInterface $tenantPreferencesRepository,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        //
        // skip to process event if it is not allowed with configuration
        $message = $envelope->getMessage();
        if (!$this->isAllowed($message)) {
            return $envelope;
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function isAllowed(object $message): bool
    {
        [$action, $configName] = match (true) {
            $message instanceof ExecutionControlMessage => [
                $message->getControlType()->value,
                $message->getActorRole() === DeliveryExecutionActorRole::ROLE_TEST_TAKER
                    ? 'testTakerActions'
                    : 'systemActions',
            ],
            $message instanceof DeliveryExecutionAcsLogMessage => [
                is_array($message->acsControl)
                    ? $message->acsControl['action']
                    : $message->acsControl->getAction(),
                'proctorActions',
            ],
            $message instanceof ExecutionLogMessage => [ExecutionLogMessage::DEFAULT_ACTION_TYPE, 'systemActions'],
            default => [null, null],
        };

        if (empty($action)) {
            return true;
        }

        $tenantAware = $message instanceof DeliveryExecutionAcsLogMessage
            ? DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($message->deliveryExecutionId)
            : $message->getDeliveryExecution();

        $tenantConfiguration = $this->tenantPreferencesRepository->findAssessmentLogSettings($tenantAware);
        $config = $tenantConfiguration[$configName] ?? [];

        return in_array(self::ANY_EVENT_MASK, $config, true)
            || in_array($action, $config, true);
    }
}
