<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Service\ExternalTimerService;
use App\TestRunner\Service\RealTimeService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\ExternalTimerDefinitionTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use Monolog\Logger;
use OAT\Library\TaoTimerClient\Client\DeleteTimerException;
use OAT\Library\TaoTimerClient\Client\GetTimerException;
use OAT\Library\TaoTimerClient\Model\TimerDetail;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface as OATTimerServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiDuration;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentSection;
use qtism\data\AssessmentSectionCollection;
use qtism\data\AssessmentTest;
use qtism\data\TestPart;
use qtism\data\TimeLimits;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionStore;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\DurationStore;
use qtism\runtime\tests\Route;
use qtism\runtime\tests\RouteItem;
use qtism\runtime\tests\RouteItemCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExternalTimerServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;
    use ExternalTimerDefinitionTestingTrait;

    private const TENANT_ID = 'tenantId';

    private readonly ExternalTimerService $subject;
    private DeliveryExecution|MockObject $deliveryExecutionMock;
    private readonly RouteItem|MockObject $routeItemMock;
    private readonly AssessmentTestSession|MockObject $testSessionMock;
    private readonly OATTimerServiceInterface|MockObject $oatTimerServiceMock;
    private readonly RealTimeService|MockObject $realTimeServiceMock;
    private readonly TestPart|MockObject $testPartMock;
    private readonly AssessmentSectionCollection $assessmentSectionMocks;
    private readonly AssessmentItemRef|MockObject $assessmentItemMock;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestLogHandler();

        $this->createDeliveryExecutionMock();
        $this->createRouteItemMock();
        $this->createTestSessionMock();
        $this->createOATTimerService();
        $this->createRealTimeServiceMock();

        $deliveryExecutionPropertyService = $this->createMock(DeliveryExecutionPropertyService::class);
        $deliveryExecutionPropertyService->method('fetchTestSession')->willReturn($this->testSessionMock);

        $this->subject = new ExternalTimerService(
            static::getContainer()->get(LoggerInterface::class),
            $this->oatTimerServiceMock,
            $this->realTimeServiceMock,
        );
    }

    public function testFetchOrCreateRemoteTimerNotRunIfTimerExist(): void
    {
        $this->expectTimeConstraint(10);
        $this->oatTimerServiceMock
            ->expects($this->once())
            ->method('createTimer')
            ->with($this->deliveryExecutionMock->getId());

        $this->subject->fetchOrCreateRemoteTimer($this->deliveryExecutionMock, $this->testSessionMock);
    }

    public function testFetchOrCreateRemoteTimerIfNoTimerWeCreateIt(): void
    {
        $this->expectTimeConstraint(10);
        $this->oatTimerServiceMock
            ->expects($this->once())
            ->method('createTimer')
            ->with($this->deliveryExecutionMock->getId())
            ->will($this->throwException(new GetTimerException()))
        ;
        $this->subject->fetchOrCreateRemoteTimer($this->deliveryExecutionMock, $this->testSessionMock);

        $this->assertHasLogRecordWithMessage(
            sprintf('Creating external timer for %s', $this->deliveryExecutionMock->getId()),
            Logger::DEBUG,
        );
    }

    public function testFetchOrCreateRemoteTimerTimerDefinitionCreatedWell(): void
    {
        $this->expectTimeConstraint(10);

        $this->oatTimerServiceMock
            ->expects($this->once())
            ->method('createTimer')
            ->with($this->deliveryExecutionMock->getId())
            ->will($this->throwException(new GetTimerException()))
        ;

        $timerDefinition = $this->subject->fetchOrCreateRemoteTimer($this->deliveryExecutionMock, $this->testSessionMock);

        $this->assertHasLogRecordWithMessage(
            sprintf('Creating external timer for %s', $this->deliveryExecutionMock->getId()),
            Logger::DEBUG,
        );

        $parts = $timerDefinition->getTestParts();
        $items = $timerDefinition->getItems();
        $sections = $timerDefinition->getSections();
        $extra = $timerDefinition->getExtra();

        $this->assertNotEmpty($parts);
        $this->assertNotEmpty($sections);
        $this->assertNotEmpty($items);
        $this->assertNull($extra);


        // test parts not repeated
        $partsIds = array_map(fn(TimerDetail $timerDetail) => $timerDetail->getId(), $parts);
        $uniqPartsIds = array_unique($partsIds);

        $this->assertEquals($partsIds, $uniqPartsIds);
    }

    public function testFetchOrCreateRemoteTimerTimerDefinitionUsedFromExtraData(): void
    {
        $this->expectTimeConstraint(10);

        $inputTimerDefinition = $this->timerDataExample;
        $this->createDeliveryExecutionMock($inputTimerDefinition);

        $this->oatTimerServiceMock
            ->expects($this->once())
            ->method('createTimer')
            ->with($this->deliveryExecutionMock->getId())
            ->will($this->throwException(new GetTimerException()))
        ;

        $timerDefinition = $this->subject->fetchOrCreateRemoteTimer($this->deliveryExecutionMock, $this->testSessionMock);

        $this->assertHasLogRecordWithMessage(
            sprintf('Creating external timer for %s', $this->deliveryExecutionMock->getId()),
            Logger::DEBUG,
        );

        $test  = $timerDefinition->getTest();
        $parts = $timerDefinition->getTestParts();
        $items = $timerDefinition->getItems();
        $sections = $timerDefinition->getSections();
        $extra = $timerDefinition->getExtra();

        $this->assertNotEmpty($test);
        $this->assertNotEmpty($parts);
        $this->assertNotEmpty($test);
        $this->assertNotEmpty($sections);
        $this->assertNull($extra);

        $this->assertEquals($inputTimerDefinition['test']['id'], $test->getId());
        $this->assertEquals($inputTimerDefinition['test']['maxTime'], $test->getMaxTime());
        $this->assertEquals($inputTimerDefinition['test']['maxTimeRemaining'], $test->getMaxTimeRemaining());

        $this->assertEquals($inputTimerDefinition['testParts'][0]['id'], $parts[0]->getId());
        $this->assertEquals($inputTimerDefinition['testParts'][0]['maxTime'], $parts[0]->getMaxTime());
        $this->assertEquals($inputTimerDefinition['testParts'][0]['maxTimeRemaining'], $parts[0]->getMaxTimeRemaining());

        $this->assertEquals($inputTimerDefinition['sections'][0]['id'], $sections[0]->getId());
        $this->assertEquals($inputTimerDefinition['sections'][0]['maxTime'], $sections[0]->getMaxTime());
        $this->assertEquals($inputTimerDefinition['sections'][0]['maxTimeRemaining'], $sections[0]->getMaxTimeRemaining());

        $this->assertEquals($inputTimerDefinition['items'][0]['id'], $items[0]->getId());
        $this->assertEquals($inputTimerDefinition['items'][0]['maxTime'], $items[0]->getMaxTime());
        $this->assertEquals($inputTimerDefinition['items'][0]['maxTimeRemaining'], $items[0]->getMaxTimeRemaining());

        // test parts not repeated
        $partsIds = array_map(fn(TimerDetail $timerDetail) => $timerDetail->getId(), $parts);
        $uniqPartsIds = array_unique($partsIds);

        $this->assertEquals($partsIds, $uniqPartsIds);
    }

    public function testTimerShouldNotCreatedIfDurationEmpty(): void
    {
        $this->deliveryExecutionMock->addIsTimerEnabledState(false);
        $timerDefinition = $this->subject->fetchOrCreateRemoteTimer($this->deliveryExecutionMock, $this->testSessionMock);

        $this->assertHasNoLogRecordWithMessage(
            sprintf('Creating external timer for %s', $this->deliveryExecutionMock->getId()),
            Logger::DEBUG,
        );

        $this->assertEmpty($timerDefinition);
    }

    public function testDeleteServerTimerDetectInvalid()
    {
        $this->expectTimeConstraint(10);
        $this->oatTimerServiceMock
            ->expects($this->once())
            ->method('deleteTimer')
            ->with($this->deliveryExecutionMock->getId())
            ->will($this->throwException(new DeleteTimerException('error')));

        $this->subject->deleteServerTimer($this->deliveryExecutionMock);

        $this->assertHasLogRecordWithMessage(
            sprintf('Timer cannot be removed for deliveryExecutionId [ %s ], reason: error', $this->deliveryExecutionMock->getId()),
            Logger::WARNING,
        );
    }

    public function testDeleteServerTimerOk(): void
    {
        $this->expectTimeConstraint(10);
        $this->oatTimerServiceMock
            ->expects($this->once())
            ->method('deleteTimer')
            ->with($this->deliveryExecutionMock->getId());

        $this->subject->deleteServerTimer($this->deliveryExecutionMock);
    }

    public function testTimerDisableForReviewMode(): void
    {
        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock->method('isReview')->willReturn(true);

        $timer = $this->subject->getServerTimer($deliveryExecutionMock);
        $this->assertNull($timer);
    }

    private function createDeliveryExecutionMock(?array $externalTimerDefinition = null): void
    {
        $extraStateData = DeliveryExecutionExtraStateData::fromArray([
            'externalTimerDefinition' => $this->createExternalDefinitionTimerFromArray($externalTimerDefinition),
        ]);

        $this->deliveryExecutionMock = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#' . self::TENANT_ID,
            'deliveryId',
            self::TENANT_ID,
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

        $this->testPartMock = $this->createMock(TestPart::class);
        $this->testPartMock->method('getIdentifier')->willReturn('partIdentifier');
        $this->testPartMock->method('getNavigationMode')->willReturn(1);

        $firstAssessmentSectionMock = $this->createMock(AssessmentSection::class);
        $firstAssessmentSectionMock->method('getIdentifier')->willReturn('firstSectionIdentifier');

        $secondAssessmentSectionMock = $this->createMock(AssessmentSection::class);
        $secondAssessmentSectionMock->method('getIdentifier')->willReturn('secondSectionIdentifier');

        $this->assessmentItemMock = $this->createMock(AssessmentItemRef::class);
        $this->assessmentItemMock->method('getIdentifier')->willReturn('itemRefIdentifier');

        $this->assessmentSectionMocks = new AssessmentSectionCollection([$firstAssessmentSectionMock, $secondAssessmentSectionMock]);

        $this->routeItemMock->method('getAssessmentTest')->willReturn($assessmentTestMock);
        $this->routeItemMock->method('getTestPart')->willReturn($this->testPartMock);
        $this->routeItemMock->method('getAssessmentSections')->willReturn($this->assessmentSectionMocks);
        $this->routeItemMock->method('getAssessmentItemRef')->willReturn($this->assessmentItemMock);
        $this->routeItemMock->method('getOccurence')->willReturn(1);
    }

    private function createTestSessionMock(): void
    {
        $arrayAccessMock = $this->createMock(Route::class);
        $arrayAccessMock->method('current')->willReturn($this->routeItemMock);
        $arrayAccessMock->method('getAllRouteItems')->willReturn(new RouteItemCollection([$this->routeItemMock]));

        $itemSession = $this->createMock(AssessmentItemSession::class);

        $itemSessionStore = $this->createMock(AssessmentItemSessionStore::class);
        $itemSessionStore->method('getAssessmentItemSession')->willReturn($itemSession);

        $this->testSessionMock = $this->createMock(AssessmentTestSession::class);
        $this->testSessionMock->method('getAssessmentItemSessionStore')->willReturn($itemSessionStore);
        $this->testSessionMock->method('getRoute')->willReturn($arrayAccessMock);
    }

    private function createOATTimerService(): void
    {
        $this->oatTimerServiceMock = $this->createMock(OATTimerServiceInterface::class);
        $this->oatTimerServiceMock->method('createTimer');
    }

    private function createRealTimeServiceMock(): void
    {
        $this->realTimeServiceMock = $this->createMock(RealTimeService::class);
        $this->realTimeServiceMock
            ->method('isEnabled')
            ->willReturn(true);
    }

    private function expectTimeConstraint(int $maxTime): void
    {
        $duration = $this->createMock(QtiDuration::class);
        $duration
            ->method('getSeconds')
            ->with(true)
            ->willReturn($maxTime);

        $timeLimit = $this->createMock(TimeLimits::class);
        $timeLimit
            ->method('hasMaxTime')
            ->willReturn($maxTime > 0);
        $timeLimit
            ->method('getMaxTime')
            ->willReturn($duration);

        $this->testPartMock
            ->method('getTimeLimits')
            ->willReturn($timeLimit);

        foreach ($this->assessmentSectionMocks as $assessmentSectionMock) {
            $assessmentSectionMock
                ->method('getTimeLimits')
                ->willReturn($timeLimit);
        }

        $this->assessmentItemMock
            ->method('getTimeLimits')
            ->willReturn($timeLimit);
    }
}
