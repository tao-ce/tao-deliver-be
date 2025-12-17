<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\TestRunner\Service\ActionIdProvider;
use PHPUnit\Framework\TestCase;

class ActionIdProviderTest extends TestCase
{
    private const EXPECTED_ACTION_ID = '1';
    private ActionIdProvider $subject;

    protected function setUp(): void
    {
        $this->subject = new ActionIdProvider();
    }

    public function testGetterAndSetter()
    {
        $this->subject->set(self::EXPECTED_ACTION_ID);
        $this->assertEquals(self::EXPECTED_ACTION_ID, $this->subject->get());
        $this->subject->set(null);
        self::assertNull($this->subject->get());
    }
}
