<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\DeliveryExecution;

use qtism\runtime\tests\AssessmentItemSessionStore;
use SplObjectStorage;

class PublicSessionStore extends AssessmentItemSessionStore
{
    public function __construct(private readonly AssessmentItemSessionStore $store)
    {
        parent::__construct();
    }

    public function getShelves(): SplObjectStorage
    {
        return $this->store->getShelves();
    }
}
