<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Render\Twig;

use App\Qti\Render\Twig\QtiExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class QtiExtensionTest extends TestCase
{
    /** @var QtiExtension */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new QtiExtension();
    }

    public function testGetFunctions(): void
    {
        $this->assertIsArray($this->subject->getFunctions());

        /** @var TwigFunction $getPrintedVariableTwigFunction */
        $getPrintedVariableTwigFunction = $this->subject->getFunctions()[0];
        $this->assertInstanceOf(TwigFunction::class, $getPrintedVariableTwigFunction);
        $this->assertEquals('getPrintedVariable', $getPrintedVariableTwigFunction->getName());
        $this->assertInstanceOf(QtiExtension::class, $getPrintedVariableTwigFunction->getCallable()[0]);
        $this->assertEquals('getPrintedVariable', $getPrintedVariableTwigFunction->getCallable()[1]);
    }
}
