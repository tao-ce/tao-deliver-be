<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Validator\AbstractRequestValidator;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints;

abstract class DeliveryExecutionAwareRequestValidator extends AbstractRequestValidator
{
    protected function getDeliveryExecutionConstraints(): array
    {
        return [
            new Constraints\NotBlank(),
            new Constraints\Collection([
                'deliveryExecutionId' => new Constraints\NotBlank(),
                'ltiLaunchParameters' => new Constraints\Collection([
                    'result_id' => new Constraints\NotBlank(),
                ], allowExtraFields: true),
                "status" => new Constraints\Choice([
                    'choices' => [
                        DeliveryExecutionStatus::STATUS_INITIAL->value,
                        DeliveryExecutionStatus::STATUS_INTERACTING->value,
                        DeliveryExecutionStatus::STATUS_SUSPENDED->value,
                        DeliveryExecutionStatus::STATUS_CLOSED->value,
                        DeliveryExecutionStatus::STATUS_TERMINATED->value,
                    ],
                ]),
                'startedAt' => [
                    new Constraints\NotBlank(),
                    new Constraints\DateTime(DateTimeInterface::RFC3339_EXTENDED),
                ],
            ], allowExtraFields: true),
        ];
    }
}
