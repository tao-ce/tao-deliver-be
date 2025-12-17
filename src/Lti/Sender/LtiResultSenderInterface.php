<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Sender;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\ResultExtractionMessage;

interface LtiResultSenderInterface
{
    public function send(DeliveryExecution $deliveryExecution, array $resultData, ResultExtractionMessage $message): void;
}
