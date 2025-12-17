<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Workflow;

use App\Domain\Publication\Model\Publication;

class PublicationStateMachineTest extends AbstractStateMachine
{
    protected function getStateMachineIdentifier(): string
    {
        return 'publication';
    }

    public function getExpectedPlaces(): array
    {
        return [
            Publication::STATUS_CREATED,
            Publication::STATUS_STARTED,
            Publication::STATUS_SUCCESS,
            Publication::STATUS_FAILED,
        ];
    }

    public function getExpectedTransitions(): array
    {
        return [
            'process_start' => [
                'froms' => [Publication::STATUS_CREATED, Publication::STATUS_FAILED],
                'tos' => [Publication::STATUS_STARTED],
            ],
            'process_success' => [
                'froms' => [Publication::STATUS_STARTED],
                'tos' => [Publication::STATUS_SUCCESS],
            ],
            'process_failure' => [
                'froms' => [Publication::STATUS_STARTED],
                'tos' => [Publication::STATUS_FAILED],
            ],
        ];
    }
}
