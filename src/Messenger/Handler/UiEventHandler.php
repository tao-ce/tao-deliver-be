<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Messenger\Handler;

use App\Messenger\Message\DeliveryExecutionUIEventMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class UiEventHandler
{
    public function __construct(private LoggerInterface $auditDeliveryExecutionLogger)
    {
    }

    public function __invoke(DeliveryExecutionUIEventMessage $message): void
    {
        $message->getDeliveryExecution()->pushUiEvents($message);
        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - pushed %u UI events',
                $message->getDeliveryExecutionId(),
                count($message->getEvents()),
            ),
        );
    }
}
