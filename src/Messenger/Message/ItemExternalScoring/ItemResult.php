<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\ItemExternalScoring;

use App\Qti\Service\Contract\ArgumentItemResultInterface;
use Carbon\Carbon;
use DateTimeInterface;

class ItemResult implements ArgumentItemResultInterface
{
    private string $id;
    private DateTimeInterface $timestamp;
    private array $outcomeVariableList;
    private ?array $overallComment = null;

    public static function fromArray(array $input): self
    {
        $itemResult = new self();

        $itemResult->id = $input['identifier'];
        $itemResult->timestamp = empty($input['datestamp']) ? Carbon::now() : new Carbon($input['datestamp']);
        $outcomeVariableList = array_map(
            [OutcomeVariable::class, 'fromArray'],
            $input['outcomeVariable'] ?? [],
        );

        $itemResult->outcomeVariableList = array_reduce(
            $outcomeVariableList,
            function ($outcomeList, OutcomeVariable $outcomeVariable) {
                $outcomeList[$outcomeVariable->getId()] = $outcomeVariable;
                return $outcomeList;
            },
        );
        $itemResult->overallComment = $input['overallComment'] ?? null;

        return $itemResult;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getOutcomeVariableAssoc(): array
    {
        return $this->outcomeVariableList;
    }

    public function getOverallComment(): ?array
    {
        return $this->overallComment;
    }
}
