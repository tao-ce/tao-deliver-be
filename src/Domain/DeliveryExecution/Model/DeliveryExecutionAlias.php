<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;

class DeliveryExecutionAlias extends AbstractDocument
{
    private string $deliveryExecutionId;

    public function __construct(
        string $id,
        ?string $deliveryExecutionId,
    ) {
        $this->id = $id;

        if (null !== $deliveryExecutionId) {
            $this->setDeliveryExecutionId($deliveryExecutionId);
        }
    }

    public function getDeliveryExecutionId(): string
    {
        return $this->deliveryExecutionId;
    }

    public function setDeliveryExecutionId(string $deliveryExecutionId): self
    {
        $this->deliveryExecutionId = $deliveryExecutionId;
        $this->addUpdate('deliveryExecutionId');

        return $this;
    }
}
