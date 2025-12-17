<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Generator;

use App\Generator\UuidGenerator;
use PHPUnit\Framework\TestCase;

class UuidGeneratorTest extends TestCase
{
    /** @var UuidGenerator */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new UuidGenerator();
    }

    public function testGeneratedUuidIsAString(): void
    {
        $this->assertIsString($this->subject->generate());
    }

    public function testGeneratedUuidIsUnique(): void
    {
        $this->assertNotEquals(
            $this->subject->generate(),
            $this->subject->generate(),
        );
    }
}
