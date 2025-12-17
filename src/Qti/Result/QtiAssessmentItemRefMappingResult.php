<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Result;

class QtiAssessmentItemRefMappingResult
{
    /** @var string */
    private $identifier;

    /** @var string|null */
    private $label;

    /** @var string|null */
    private $title;

    public function __construct(string $identifier, ?string $label = null, ?string $title = null)
    {
        $this->identifier = $identifier;
        $this->label = $label;
        $this->title = $title;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function normalize(): array
    {
        return [
            'itemIdentifier' => $this->identifier,
            'itemLabel' => $this->label,
            'itemTitle' => $this->title,
        ];
    }
}
