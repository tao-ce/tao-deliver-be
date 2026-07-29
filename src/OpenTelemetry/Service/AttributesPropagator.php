<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\OpenTelemetry\Service;

use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use PetitPress\GpsMessengerBundle\Transport\Stamp\AttributesStamp;
use PetitPress\GpsMessengerBundle\Transport\Stamp\GpsReceivedStamp;
use Symfony\Component\Messenger\Envelope;

class AttributesPropagator implements PropagationGetterInterface, PropagationSetterInterface
{
    public function keys($carrier): array
    {
        assert($carrier instanceof Envelope);

        return array_keys($carrier->last(GpsReceivedStamp::class)?->getGpsMessage()->attributes());
    }

    public function get($carrier, string $key): ?string
    {
        assert($carrier instanceof Envelope);

        return $carrier->last(GpsReceivedStamp::class)?->getGpsMessage()->attribute($key);
    }

    public function set(&$carrier, string $key, string $value): void
    {
        assert($carrier instanceof Envelope);

        $carrier = $carrier->with(
            new AttributesStamp(
                [$key => $value] + ($carrier->last(AttributesStamp::class)?->getAttributes() ?? []),
            ),
        );
    }
}
