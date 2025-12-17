<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionLogServiceInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Messenger\Message\DeliveryExecution\ExecutionLogMessage;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use Carbon\Carbon;
use DateTimeImmutable;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DeliveryExecutionLogService implements DeliveryExecutionLogServiceInterface
{
    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly MessageBusInterface $messageBus,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function log(DeliveryExecution $deliveryExecution, DeliveryExecutionActorRole $issuer, string $message): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $route = $testSession->getRoute();

        $this->messageBus->dispatch(
            new ExecutionLogMessage(
                $issuer,
                new DeliveryExecutionActorIdentity(
                    $deliveryExecution->getUserId(),
                    $deliveryExecution->getLtiLaunchParameters()['user_name'] ?? '',
                    $issuer,
                    $request?->headers->get('User-Agent'),
                    $request?->getClientIp(),
                ),
                $deliveryExecution,
                Carbon::now(),
                $message,
                $route->valid() ? $route->current()->getAssessmentItemRef()->getIdentifier() : null,
            ),
        );
    }
}
