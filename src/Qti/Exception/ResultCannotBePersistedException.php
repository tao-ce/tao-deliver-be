<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Exception;

use RuntimeException;
use Throwable;

class ResultCannotBePersistedException extends RuntimeException
{
    public static function createFromResultId(string $resultId, ?Throwable $previous = null): static
    {
        return new static(
            message: sprintf('Cannot persist results of %s', $resultId),
            previous: $previous,
        );
    }
}
