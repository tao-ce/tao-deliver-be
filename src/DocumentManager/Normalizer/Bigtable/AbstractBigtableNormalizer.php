<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer\Bigtable;

use OAT\Bundle\DocumentManagerBundle\Normalizer\DocumentNormalizerInterface;

abstract class AbstractBigtableNormalizer implements DocumentNormalizerInterface
{
    public const DATA_COLUMN_FAMILY = 'data';
    public const DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    public static function getPriority(): int
    {
        return 1;
    }
}
