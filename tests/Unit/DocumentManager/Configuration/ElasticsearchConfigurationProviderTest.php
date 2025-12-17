<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Configuration;

use App\DocumentManager\Configuration\ElasticsearchConfigurationProvider;
use PHPUnit\Framework\TestCase;

class ElasticsearchConfigurationProviderTest extends TestCase
{
    private ElasticsearchConfigurationProvider $subject;

    protected function setUp(): void
    {
        $this->subject = new ElasticsearchConfigurationProvider('http://localhost', '9200', 'apiKey');
    }

    public function testProvideConfiguration(): void
    {
        $expected = [
            'hosts' => ['http://localhost:9200'],
            'apiKey' => ['apiKey', null],
        ];

        $this->assertSame($expected, $this->subject->provideConfiguration());
    }

    public function testProvideQuiet(): void
    {
        $this->assertFalse($this->subject->provideQuiet());
    }
}
