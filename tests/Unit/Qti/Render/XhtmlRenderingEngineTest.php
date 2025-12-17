<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Render;

use App\Qti\Render\PrintedVariableRenderer;
use App\Qti\Render\XhtmlRenderingEngine;
use PHPUnit\Framework\TestCase;
use qtism\data\content\PrintedVariable;
use qtism\runtime\rendering\markup\xhtml\XhtmlRenderingEngine as BaseXhtmlRenderingEngine;

class XhtmlRenderingEngineTest extends TestCase
{
    /** @var XhtmlRenderingEngine */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new XhtmlRenderingEngine();
    }

    public function testInstanceOf(): void
    {
        $this->assertInstanceOf(BaseXhtmlRenderingEngine::class, $this->subject);
    }

    public function testRegisteredPrintedVariableRenderer(): void
    {
        $this->assertInstanceOf(PrintedVariableRenderer::class, $this->subject->getRenderer(
            $this->getPrintedVariableMock(),
        ));
    }

    private function getPrintedVariableMock(): PrintedVariable
    {
        return new PrintedVariable('id');
    }
}
