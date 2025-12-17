<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Render;

use App\Qti\Render\PrintedVariableRenderer;
use PHPUnit\Framework\TestCase;
use qtism\runtime\rendering\markup\xhtml\PrintedVariableRenderer as BasePrintedVariableRenderer;

class PrintedVariableRendererTest extends TestCase
{
    /** @var PrintedVariableRenderer */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new PrintedVariableRenderer();
    }

    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(BasePrintedVariableRenderer::class, $this->subject);
    }
}
