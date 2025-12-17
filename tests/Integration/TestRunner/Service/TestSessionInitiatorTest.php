<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Service\TestSessionInitiator;
use App\TestRunner\Service\TestSessionNavigator;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use qtism\runtime\common\State;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TestSessionInitiatorTest extends KernelTestCase
{
    use QtiTestingTrait;
    use DomainTestingTrait;

    private TestSessionInitiator $subject;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;

    public function setUp(): void
    {
        static::bootKernel();

        $this->subject = static::getContainer()->get(TestSessionInitiator::class);
        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
    }

    public function testInitializesTestSession(): void
    {
        $deliveryExecution = $this->createDeliveryExecution();

        $this->subject->init($deliveryExecution);

        $this->assertTestSessionIsInitialized($deliveryExecution);
    }

    public function testReinitializeTestSessionWhenForceParamIsProvided(): void
    {
        $deliveryExecution = $this->createDeliveryExecution();

        $this->subject->init($deliveryExecution);

        $this->endTest($deliveryExecution);

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $this->assertEquals(AssessmentTestSessionState::CLOSED, $testSession->getState());

        $this->subject->init($deliveryExecution, true);

        $this->assertTestSessionIsInitialized($deliveryExecution);
    }

    public function testAvoidCallingBeginAttemptWhenReinitializingTestSession(): void
    {
        $deliveryExecution = $this->createDeliveryExecution('BasicNonLinearOneAttempt');

        $this->subject->init($deliveryExecution);

        $this->attemptFirstItem($deliveryExecution);

        $this->subject->init($deliveryExecution, true);

        $this->assertTestSessionIsInitialized($deliveryExecution);
    }

    public function testInitializesExistingTestSessionWhenFirstItemHasNoMaxAttemptsLeft(): void
    {
        $deliveryExecution = $this->createDeliveryExecution('BasicNonLinearOneAttempt');

        $this->subject->init($deliveryExecution);

        $this->attemptFirstItem($deliveryExecution);

        $this->subject->init($deliveryExecution);

        $this->assertTestSessionIsInitialized($deliveryExecution);
    }

    private function createDeliveryExecution(string $testPackageName = 'Basic'): DeliveryExecution
    {
        $this->copyCompiledTestToStorage(['compact-test.xml'], $testPackageName);

        return $this->createTestDeliveryExecution(
            "userId#$testPackageName#resultId#tenantId",
            $testPackageName,
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );
    }

    private function endTest(DeliveryExecution $deliveryExecution): void
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $testSessionNavigator = static::getContainer()->get(TestSessionNavigator::class);

        // Navigate through the test until the last item (Basic test has 3 items)
        $testSessionNavigator->navigate(
            $deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $testSessionNavigator->navigate(
            $deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $testSessionNavigator->navigate(
            $deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
    }

    private function attemptFirstItem(DeliveryExecution $deliveryExecution)
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $testSession->getCurrentAssessmentItemSession()->endAttempt(new State());

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
    }

    private function assertTestSessionIsInitialized(DeliveryExecution $deliveryExecution): void
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $expectedRoutePosition = 0;

        $this->assertEquals(AssessmentTestSessionState::INTERACTING, $testSession->getState());
        $this->assertEquals($expectedRoutePosition, $testSession->getRoute()->getPosition());
    }
}
