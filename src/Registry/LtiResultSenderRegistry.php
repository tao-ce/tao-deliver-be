<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Registry;

use App\Lti\Sender\LtiResultSenderInterface;

class LtiResultSenderRegistry
{
    public function __construct(private iterable $senders = [])
    {
    }

    /**
     * @return LtiResultSenderInterface[]
     */
    public function getSenders(): iterable
    {
        return $this->senders;
    }
}
