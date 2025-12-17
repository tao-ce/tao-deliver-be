<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Qti\Extractor\QtiResultExtractorPreProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Environment\FeatureFlagAdapterInterface;
use App\Qti\Extractor\QtiResultExtractorPreProcessor\TouchedItemQtiResultExtractorPreProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiBoolean;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\data\AssessmentItemRef;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionCollection;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\Route;
use qtism\runtime\tests\RouteItem;
use qtism\runtime\tests\RouteItemCollection;
use Throwable;

class TouchedItemQtiResultExtractorPreProcessorTest extends TestCase
{
    private TouchedItemQtiResultExtractorPreProcessor $subject;
    private LoggerInterface|MockObject $loggerMock;
    private FeatureFlagAdapterInterface|MockObject $featureFlagAdapter;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->featureFlagAdapter = $this->createMock(FeatureFlagAdapterInterface::class);
        $this->subject = new TouchedItemQtiResultExtractorPreProcessor(
            $this->loggerMock,
            $this->featureFlagAdapter,
        );
    }

    public function testProcess(): void
    {
        $this->featureFlagAdapter
            ->method('isEnabled')
            ->with('tenantId', 'ADD_CUSTOM_TOUCHED_OUTCOME_VARIABLE_TO_RESULT')
            ->willReturn(true);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getExtraStateData')
            ->willReturn(DeliveryExecutionExtraStateData::fromArray([
                'itemStates' => [
                    'item1' => '{"touched": true}',
                ],
            ]));
        $deliveryExecutionMock
            ->method('getTenantId')
            ->willReturn('tenantId');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->method('getIdentifier')
            ->willReturn('item1');

        $routeItemMock = $this->createMock(RouteItem::class);
        $routeItemMock
            ->method('getAssessmentItemRef')
            ->willReturn($assessmentItemRefMock);

        $routeMock = $this->createMock(Route::class);
        $routeMock
            ->method('getAllRouteItems')
            ->willReturn(new RouteItemCollection([$routeItemMock]));

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->method('getRoute')
            ->willReturn($routeMock);

        $assessmentItemSession = $this->createMock(AssessmentItemSession::class);
        $assessmentItemSession
            ->expects($this->once())
            ->method('setVariable')
            ->with(new OutcomeVariable(
                'TOUCHED',
                Cardinality::SINGLE,
                BaseType::BOOLEAN,
                new QtiBoolean(true),
            ));

        $assessmentItemSessionCollectionMock = $this->createMock(AssessmentItemSessionCollection::class);
        $assessmentItemSessionCollectionMock
            ->method('current')
            ->willReturn($assessmentItemSession);
        $assessmentItemSessionCollectionMock
            ->method('valid')
            ->willReturn(true);

        $testSessionMock
            ->method('getAssessmentItemSessions')
            ->with('item1')
            ->willReturn($assessmentItemSessionCollectionMock);

        $this->subject->process($deliveryExecutionMock, $testSessionMock);
    }

    public function testProcessWhenFeatureIsDisabled(): void
    {
        $this->featureFlagAdapter
            ->method('isEnabled')
            ->with('tenantId', 'ADD_CUSTOM_TOUCHED_OUTCOME_VARIABLE_TO_RESULT')
            ->willReturn(false);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getTenantId')
            ->willReturn('tenantId');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects($this->never())
            ->method('getRoute');

        $this->loggerMock
            ->expects($this->never())
            ->method('error');

        $this->subject->process($deliveryExecutionMock, $testSessionMock);
    }

    public function testProcessWhenItemStateIsNull(): void
    {
        $this->featureFlagAdapter
            ->method('isEnabled')
            ->with('tenantId', 'ADD_CUSTOM_TOUCHED_OUTCOME_VARIABLE_TO_RESULT')
            ->willReturn(true);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getExtraStateData')
            ->willReturn(DeliveryExecutionExtraStateData::fromArray([
                'itemStates' => [
                    'item1' => null,
                ],
            ]));
        $deliveryExecutionMock
            ->method('getTenantId')
            ->willReturn('tenantId');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->method('getIdentifier')
            ->willReturn('item1');

        $routeItemMock = $this->createMock(RouteItem::class);
        $routeItemMock
            ->method('getAssessmentItemRef')
            ->willReturn($assessmentItemRefMock);

        $routeMock = $this->createMock(Route::class);
        $routeMock
            ->method('getAllRouteItems')
            ->willReturn(new RouteItemCollection([$routeItemMock]));

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->method('getRoute')
            ->willReturn($routeMock);

        $assessmentItemSession = $this->createMock(AssessmentItemSession::class);
        $assessmentItemSession
            ->expects($this->once())
            ->method('setVariable')
            ->with(new OutcomeVariable(
                'TOUCHED',
                Cardinality::SINGLE,
                BaseType::BOOLEAN,
                new QtiBoolean(false),
            ));

        $assessmentItemSessionCollectionMock = $this->createMock(AssessmentItemSessionCollection::class);
        $assessmentItemSessionCollectionMock
            ->method('current')
            ->willReturn($assessmentItemSession);
        $assessmentItemSessionCollectionMock
            ->method('valid')
            ->willReturn(true);

        $testSessionMock
            ->method('getAssessmentItemSessions')
            ->with('item1')
            ->willReturn($assessmentItemSessionCollectionMock);

        $this->subject->process($deliveryExecutionMock, $testSessionMock);
    }

    public function testProcessWhenItemSessionIsInvalid(): void
    {
        $this->featureFlagAdapter
            ->method('isEnabled')
            ->with('tenantId', 'ADD_CUSTOM_TOUCHED_OUTCOME_VARIABLE_TO_RESULT')
            ->willReturn(true);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getExtraStateData')
            ->willReturn(DeliveryExecutionExtraStateData::fromArray([
                'itemStates' => [
                    'item1' => '{"touched": true}',
                ],
            ]));
        $deliveryExecutionMock
            ->method('getTenantId')
            ->willReturn('tenantId');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->method('getIdentifier')
            ->willReturn('item1');

        $routeItemMock = $this->createMock(RouteItem::class);
        $routeItemMock
            ->method('getAssessmentItemRef')
            ->willReturn($assessmentItemRefMock);

        $routeMock = $this->createMock(Route::class);
        $routeMock
            ->method('getAllRouteItems')
            ->willReturn(new RouteItemCollection([$routeItemMock]));

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->method('getRoute')
            ->willReturn($routeMock);

        $assessmentItemSessionCollectionMock = $this->createMock(AssessmentItemSessionCollection::class);
        $assessmentItemSessionCollectionMock
            ->expects($this->never())
            ->method('current');
        $assessmentItemSessionCollectionMock
            ->method('valid')
            ->willReturn(false);

        $testSessionMock
            ->method('getAssessmentItemSessions')
            ->with('item1')
            ->willReturn($assessmentItemSessionCollectionMock);

        $this->subject->process($deliveryExecutionMock, $testSessionMock);
    }
}
