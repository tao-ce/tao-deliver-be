<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage;
use App\TestRunner\Service\ExternalTimerService;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use LogicException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiDuration;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentSection;
use qtism\data\AssessmentSectionCollection;
use qtism\data\AssessmentTest;
use qtism\data\TestPart;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionStore;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\DurationStore;
use qtism\runtime\tests\Route;
use qtism\runtime\tests\RouteItem;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TimerServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;

    /** @var TimerService */
    private $subject;

    /** @var DeliveryExecution */
    private $deliveryExecution;

    /** @var RouteItem|MockObject */
    private $routeItemMock;

    /** @var DurationStorage|MockObject */
    private $durationStorageMock;

    /** @var AssessmentTestSession|MockObject */
    private $testSessionMock;

    /** @var array */
    private $qtiDurationMocks;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestLogHandler();

        $this->subject = new TimerService(
            $this->createMock(ExternalTimerService::class),
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
            0.5,
        );

        $this->createDeliveryExecutionMock();
        $this->createRouteItemMock();
        $this->createQtiDurationMocks();
        $this->createTestSessionMock();
    }

    public function testBeginServerTimer(): void
    {
        $this->durationStorageMock
            ->expects($this->once())
            ->method('withStartedServerTimer')
            ->with('itemRefIdentifier')
            ->willReturn($this->durationStorageMock);

        $this->subject->beginServerTimer($this->deliveryExecution, $this->testSessionMock);
    }

    /**
     * @dataProvider endServerTimerProvider
     */
    public function testEndServerTimer(
        float $clientDuration,
        string $expectedQtiDuration,
    ): void {
        $this->durationStorageMock
            ->expects($this->once())
            ->method('withStoppedServerTimer')
            ->with('itemRefIdentifier')
            ->willReturn($this->durationStorageMock);

        foreach ($this->qtiDurationMocks as $qtiDurationMockMap) {
            /** @var MockObject|QtiDuration $qtiDurationMock */
            $qtiDurationMock = end($qtiDurationMockMap);
            $qtiDurationMock
                ->expects($this->once())
                ->method('add')
                ->with(new QtiDuration($expectedQtiDuration));
        }

        $this->subject->endServerTimer(
            $this->deliveryExecution,
            $this->testSessionMock,
            $clientDuration,
        );
    }

    public function endServerTimerProvider(): array
    {
        return [
            [1, 'PT1S'],
            [1.5, 'PT2S'],
            [2.5, 'PT3S'],
        ];
    }

    public function testEndServerTimerWhenLogicExceptionOccurs(): void
    {
        $this->durationStorageMock
            ->expects($this->once())
            ->method('withStoppedServerTimer')
            ->with('itemRefIdentifier')
            ->willThrowException(new LogicException('foo'));

        $this->subject->endServerTimer(
            $this->deliveryExecution,
            $this->testSessionMock,
            1.5,
        );

        $this->assertHasLogRecordWithMessage('[userId#deliveryId#resultId#tenantId] foo', Logger::WARNING);
    }

    private function createDeliveryExecutionMock(): void
    {
        $this->durationStorageMock = $this->createMock(DurationStorage::class);

        $extraStateData = DeliveryExecutionExtraStateData::fromArray([
            'durationStorage' => $this->durationStorageMock,
        ]);

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['ltiLaunchParams'],
            'testSession',
            $extraStateData,
        );
    }

    private function createRouteItemMock(): void
    {
        $this->routeItemMock = $this->createMock(RouteItem::class);

        $assessmentTestMock = $this->createMock(AssessmentTest::class);
        $assessmentTestMock->method('getIdentifier')->willReturn('testIdentifier');

        $testPartMock = $this->createMock(TestPart::class);
        $testPartMock->method('getIdentifier')->willReturn('partIdentifier');

        $firstAssessmentSectionMock = $this->createMock(AssessmentSection::class);
        $firstAssessmentSectionMock->method('getIdentifier')->willReturn('firstSectionIdentifier');

        $secondAssessmentSectionMock = $this->createMock(AssessmentSection::class);
        $secondAssessmentSectionMock->method('getIdentifier')->willReturn('secondSectionIdentifier');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock->method('getIdentifier')->willReturn('itemRefIdentifier');

        $sectionCollection = new AssessmentSectionCollection([$firstAssessmentSectionMock, $secondAssessmentSectionMock]);

        $this->routeItemMock->method('getAssessmentTest')->willReturn($assessmentTestMock);
        $this->routeItemMock->method('getTestPart')->willReturn($testPartMock);
        $this->routeItemMock->method('getAssessmentSections')->willReturn($sectionCollection);
        $this->routeItemMock->method('getAssessmentItemRef')->willReturn($assessmentItemRefMock);
        $this->routeItemMock->method('getOccurence')->willReturn(1);
    }

    private function createTestSessionMock(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('current')->willReturn($this->routeItemMock);

        $itemSession = $this->createMock(AssessmentItemSession::class);
        $itemSession
            ->method('offsetGet')
            ->with('duration')
            ->willreturn(end($this->qtiDurationMocks)[1]);

        $itemSessionStore = $this->createMock(AssessmentItemSessionStore::class);
        $itemSessionStore->method('getAssessmentItemSession')->willReturn($itemSession);

        $durationStore = $this->createMock(DurationStore::class);
        $durationStore
            ->method('offsetGet')
            ->willReturnMap($this->qtiDurationMocks);
        $durationStore
            ->method('offsetExists')
            ->willReturnMap(
                array_map(
                    static fn(array $map): array => [reset($map), true],
                    $this->qtiDurationMocks,
                ),
            );

        $this->testSessionMock = $this->createMock(AssessmentTestSession::class);
        $this->testSessionMock->method('getAssessmentItemSessionStore')->willReturn($itemSessionStore);
        $this->testSessionMock->method('getDurationStore')->willReturn($durationStore);
        $this->testSessionMock->method('getDurationStore')->willReturn($durationStore);
        $this->testSessionMock->method('getRoute')->willReturn($route);
    }

    private function createQtiDurationMocks(): void
    {
        $this->qtiDurationMocks = [
            ['testIdentifier', $this->createMock(QtiDuration::class)],
            ['partIdentifier', $this->createMock(QtiDuration::class)],
            ['firstSectionIdentifier', $this->createMock(QtiDuration::class)],
            ['secondSectionIdentifier', $this->createMock(QtiDuration::class)],
            ['itemRefIdentifier', $this->createMock(QtiDuration::class)],
        ];
    }
}
