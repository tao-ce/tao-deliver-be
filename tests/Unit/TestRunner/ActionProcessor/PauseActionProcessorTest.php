<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsActionProcessor\AcsPauseActionProcessor;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\PauseActionProcessor;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use OAT\Library\Lti1p3Proctoring\Model\AcsControl;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\AssessmentTestSession;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PauseActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;
    use MessengerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'pause';

    private PauseActionProcessor $subject;
    private DeliveryExecution $deliveryExecution;
    private EventDispatcherInterface|MockObject $eventDispatcherMock;
    private ProctoringAcsService|MockObject $proctoringAcsServiceMock;
    private AssessmentTestSession $testSession;
    private TimerService|MockObject $timerServiceMock;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        Carbon::setTestNow(Carbon::now());

        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $testContextGenerator = static::getContainer()->get(TestContextGenerator::class);
        $actionProcessor = new AcsPauseActionProcessor(
            $this->eventDispatcherMock,
            $this->proctoringAcsServiceMock,
            $deliveryExecutionPropertyService,
            static::getContainer()->get(DeliveryExecutionServiceInterface::class),
            $this->timerServiceMock,
            static::getContainer()->get(LtiCustomSettings::class),
            self::getContainer()->get(LtiExtraTimeHandler::class),
            self::getContainer()->get(TimerServiceInterface::class),
        );
        $this->subject = new PauseActionProcessor(
            $deliveryExecutionPropertyService,
            $testContextGenerator,
            $actionProcessor,
            $this->eventDispatcherMock,
        );

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q02/item.json',
            'Item-Q03/item.json',
        ]);

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );


        $this->testSession = $deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        $this->testSession->beginTestSession();
        $this->testSession->beginAttempt();

        $deliveryExecutionPropertyService->persistTestSession($this->testSession);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testGetName(): void
    {
        $this->assertEquals(PauseActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testItImplementsActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testProcessCorrect()
    {
        $this->deliveryExecution->setLtiLaunchParameters([
            'resource_link_id' => 'test',
        ]);

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch');

        $this->timerServiceMock
            ->expects($this->once())
            ->method('endServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
                12.34,
            );

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->with(
                $this->deliveryExecution->getId(),
                new AcsControl(
                    $this->deliveryExecution->getResourceLink(),
                    $this->deliveryExecution->getUserId(),
                    AcsControlInterface::ACTION_PAUSE,
                    Carbon::now(),
                ),
            );

        $this->subject->process($this->deliveryExecution, [
            'id' => 'pause',
            'name' => 'pause',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'resource_link_id' => 'test',
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertEquals(DeliveryExecution::STATUS_SUSPENDED, $this->deliveryExecution->getStatus());
    }

    public function testLtiParamResourceLinkRequired()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandatory resource_link.id claim is absent.');
        $this->subject->process($this->deliveryExecution, [
            'id' => 'pause',
            'name' => 'pause',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'resource_link_id' => 'test',
                'itemDuration' => '12.34',
            ]),
        ]);
    }

    public function testSuccessAccessibilityValidation(): void
    {
        $this->subject->validateAvailability(DeliveryExecution::STATUS_INTERACTING);
        self::assertTrue(true);
    }


    private function getParameters(array $overridden = []): array
    {
        return array_merge(
            [
                'itemResponse' => '{}',
                'itemState' => '{}',
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
            ],
            $overridden,
        );
    }
}
