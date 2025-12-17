<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Validator\Contract\DeliveryExecutionAwareRequestValidatorInterface;

abstract class AbstractDeliveryExecutionAwareRequestValidator extends AbstractRequestValidator implements
    DeliveryExecutionAwareRequestValidatorInterface
{
    private DeliveryExecution $deliveryExecution;

    public function setDeliveryExecution(DeliveryExecution $deliveryExecution)
    {
        $this->deliveryExecution = $deliveryExecution;
    }

    public function getDeliveryExecution(): DeliveryExecution
    {
        if (!$this->deliveryExecution) {
            throw new \RuntimeException(
                sprintf(
                    'DeliveryExecution is required set before for be used in that method [%d] please set it before somewhere.',
                    __CLASS__
                    . '::getDeliveryExecution',
                ),
            );
        }
        return $this->deliveryExecution;
    }
}
