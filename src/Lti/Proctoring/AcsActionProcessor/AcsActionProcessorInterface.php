<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;

interface AcsActionProcessorInterface
{
    public const STATUSES_MAP = [
        DeliveryExecution::STATUS_INTERACTING => AcsControlResultInterface::STATUS_RUNNING,
        DeliveryExecution::STATUS_SUSPENDED => AcsControlResultInterface::STATUS_PAUSED,
        DeliveryExecution::STATUS_TERMINATED => AcsControlResultInterface::STATUS_TERMINATED,
        DeliveryExecution::STATUS_CLOSED => AcsControlResultInterface::STATUS_COMPLETE,
    ];

    public function supports(AcsControlInterface $acsControl): bool;

    public function process(
        AcsControlInterface $acsControl,
        DeliveryExecution $deliveryExecution,
        float $itemDuration = .0,
    ): AcsControlResultInterface;
}
