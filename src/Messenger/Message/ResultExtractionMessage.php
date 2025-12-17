<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

class ResultExtractionMessage extends AbstractDeliveryExecutionAwareMessage
{
    public function __construct(private string $id, string $deliveryExecutionId, private bool $forceClosure = false)
    {
        parent::__construct($deliveryExecutionId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isForceClosure(): bool
    {
        return $this->forceClosure;
    }
}
