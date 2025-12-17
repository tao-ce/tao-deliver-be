<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Delivery\Event;

use App\Domain\Delivery\Model\Delivery;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDeliveryAwareEvent extends Event
{
    private Delivery $delivery;

    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }
}
