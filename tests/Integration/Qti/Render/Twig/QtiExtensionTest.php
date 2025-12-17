<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Qti\Render\Twig;

use PHPUnit\Framework\MockObject\MockObject;
use qtism\common\datatypes\QtiDatatype;
use qtism\common\datatypes\QtiFloat;
use qtism\common\datatypes\QtiString;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\MultipleContainer;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\common\State;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

class QtiExtensionTest extends KernelTestCase
{
    /** @var Environment */
    private $twig;

    /** @var AssessmentTestSession|MockObject */
    private $testSessionMock;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->twig = static::getContainer()->get(Environment::class);
    }

    /**
     * @dataProvider printedVariableTemplateProvider
     */
    public function testGetPrintedVariableTwigFunction(
        string $twigTemplateContent,
        int $cardinality,
        int $baseType,
        QtiDatatype $value,
        string $expectedOutput,
    ): void {
        $template = $this->twig->createTemplate($twigTemplateContent);
        $variable = new OutcomeVariable('TOTAL_SCORE', $cardinality, $baseType, $value);

        $this->testSessionMock = new State([$variable]);

        $this->assertEquals($expectedOutput, $this->twig->render($template, [
            'testSession' => $this->testSessionMock,
        ]));
    }

    public function printedVariableTemplateProvider(): array
    {
        return [
            [
                '{{ getPrintedVariable(testSession, "TOTAL_SCORE", "%d", false, 10, -1, ";", "", "=") }}',
                Cardinality::SINGLE,
                BaseType::FLOAT,
                new QtiFloat(23.0),
                '23',
            ],
            [
                '{{ getPrintedVariable(testSession, "TOTAL_SCORE", "%d", false, 10, -1, ";", "", "=") }}',
                Cardinality::MULTIPLE,
                BaseType::STRING,
                new MultipleContainer(BaseType::STRING, [new QtiString('foo'), new QtiString('bar')]),
                'foo;bar',
            ],
        ];
    }
}
