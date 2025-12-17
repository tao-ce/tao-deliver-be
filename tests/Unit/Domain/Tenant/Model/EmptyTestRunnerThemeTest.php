<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tenant\Model;

use App\Domain\Tenant\Model\EmptyTestRunnerTheme;
use PHPUnit\Framework\TestCase;

class EmptyTestRunnerThemeTest extends TestCase
{
    /** @var EmptyTestRunnerTheme */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        $this->subject = new EmptyTestRunnerTheme();
    }

    public function testGetPlatform(): void
    {
        $this->assertEmpty($this->subject->getPlatform());
    }

    public function testGetTestRunner(): void
    {
        $this->assertEmpty($this->subject->getTestRunner());
    }

    public function testGetItemRunner(): void
    {
        $this->assertEmpty($this->subject->getItemRunner());
    }

    public function testGetDefault(): void
    {
        $this->assertEmpty($this->subject->getDefault());
    }
}
