<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Service\Contract;

interface ArgumentOutcomeVariableInterface
{
    public function getId(): string;
    public function getValue(): string;
    public function getBaseType(): string;
    public function getCardinality(): string;
    public function isApplicable(): bool;
}
