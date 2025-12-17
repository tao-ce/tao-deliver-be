<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use qtism\runtime\tests\AssessmentTestSession;

class TestSessionInteractionEvent extends AbstractDeliveryExecutionAwareEvent
{
    public function __construct(
        private string $triggeredBy,
        private string $action,
        DeliveryExecution $deliveryExecution,
        private AssessmentTestSession $testSession,
        private ?array $testMap = null,
    ) {
        parent::__construct($deliveryExecution);
    }

    public function getTriggeredBy(): string
    {
        return $this->triggeredBy;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTestSession(): AssessmentTestSession
    {
        return $this->testSession;
    }

    public function getTestMap(): ?array
    {
        return $this->testMap;
    }
}
