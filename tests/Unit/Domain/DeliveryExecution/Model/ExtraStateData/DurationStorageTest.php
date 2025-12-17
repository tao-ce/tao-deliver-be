<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Model\ExtraStateData;

use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage;
use LogicException;
use PHPUnit\Framework\TestCase;

class DurationStorageTest extends TestCase
{
    /** @var DurationStorage */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new DurationStorage();
    }

    public function testGetServerDurations(): void
    {
        $this->assertEquals([], $this->subject->getServerDurations());
    }
}
