<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Domain\DeliveryExecution\Model\ExtraStateData\SubData\ExternalTimerDefinitionTrait;
use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;
use OAT\Library\TaoTimerClient\Model\TimerDefinition;
use OAT\Library\TaoTimerClient\Model\TimerDetail;

trait ExternalTimerDefinitionTestingTrait
{
    use ExternalTimerDefinitionTrait;

    protected array $timerDataExample = [
        'test' => [
            'id' => 'test',
            'started' => true,
            'minTime' => 0,
            'maxTime' => 5,
            'maxTimeRemaining' => 5,
            'initialValue' => null,
        ],
        'testParts' => [
            [
                'id' => 'test',
                'started' => true,
                'minTime' => 0,
                'maxTime' => 5,
                'maxTimeRemaining' => 5,
                'initialValue' => null,
            ],
        ],
        'sections' => [
            [
                'id' => 'test',
                'started' => true,
                'minTime' => 0,
                'maxTime' => 5,
                'maxTimeRemaining' => 5,
                'initialValue' => null,
            ],
        ],
        'items' => [
            [
                'id' => 'test',
                'started' => true,
                'minTime' => 0,
                'maxTime' => 5,
                'maxTimeRemaining' => 5,
                'initialValue' => null,
            ],
        ],
        'extra' => null,
    ];

    protected function createExternalDefinitionTimerFromArray(?array $externalTimerData = null): ?TimerDefinitionInterface
    {
        if (empty($externalTimerData)) {
            return null;
        }
        array_walk_recursive(
            $externalTimerData,
            static fn(mixed &$value, string $key) => $value = $key === 'minTime' && $value === 0 ? null : $value,
        );

        return $this->fromArrayTimerDefinition(
            ['externalTimerDefinition' => ['externalTimerData' => json_encode($externalTimerData)]],
        );
    }
}
