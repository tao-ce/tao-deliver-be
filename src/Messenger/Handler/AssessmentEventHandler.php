<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Messenger\Handler;

use App\Messenger\Message\DeliveryExecution\ExecutionControlMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AssessmentEventHandler
{
    public function __construct(private readonly NormalizerInterface $normalizer)
    {
    }

    public function __invoke(ExecutionControlMessage $message): void
    {
        $message->getDeliveryExecution()->pushAssessmentEvent(
            $this->normalizer->normalize($message),
        );
    }
}
