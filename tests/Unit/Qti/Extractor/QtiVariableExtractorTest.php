<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Extractor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Qti\Extractor\QtiVariableExtractor;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Tests\Traits\TestSessionTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\common\VariableCollection;

class QtiVariableExtractorTest extends TestCase
{
    use TestSessionTrait;

    private DeliveryExecutionPropertyService|MockObject $deliveryExecutionPropertyService;
    private $qtiVariableExtractor;

    protected function setUp(): void
    {
        $this->deliveryExecutionPropertyService = $this->createMock(DeliveryExecutionPropertyService::class);
        $this->qtiVariableExtractor = new QtiVariableExtractor($this->deliveryExecutionPropertyService);
    }

    public function testTestExtractOutcomeVariables()
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);

        $outcomeVariable = $this->createMock(OutcomeVariable::class);
        $outcomeVariable->method('getIdentifier')->willReturn('variable1');
        $outcomeVariable->method('getCardinality')->willReturn(Cardinality::SINGLE);
        $outcomeVariable->method('getBaseType')->willReturn(BaseType::INTEGER);
        $outcomeVariable->method('getValue')->willReturn(null);

        $variableCollectionMock = $this->createMock(VariableCollection::class);
        $variableCollectionMock->method('getArrayCopy')->willReturn([$outcomeVariable]);

        $testSession = $this->createTestSession($deliveryExecution->getId());
        $testSession->method('getAllVariables')->willReturn($variableCollectionMock);

        $this->deliveryExecutionPropertyService->method('fetchTestSession')->with($deliveryExecution)->willReturn($testSession);

        $variables = iterator_to_array($this->qtiVariableExtractor->extractTestOutcomeVariables($deliveryExecution));

        $this->assertCount(1, $variables);
        $this->assertEquals('variable1', $variables[0]['id']);
        $this->assertEquals('single', $variables[0]['cardinality']);
        $this->assertEquals('integer', $variables[0]['baseType']);
        $this->assertEquals(null, $variables[0]['value']);
    }
}
