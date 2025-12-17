<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Service\TestSessionNavigator;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TestSessionNavigatorTest extends KernelTestCase
{
    use QtiTestingTrait;
    use DomainTestingTrait;

    private TestSessionNavigator $subject;
    private AssessmentTestSession $testSession;
    private DeliveryExecution $deliveryExecution;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;

    public function setUp(): void
    {
        static::bootKernel();

        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->createTestSession();

        $this->subject = static::getContainer()->get(TestSessionNavigator::class);
    }

    public function testItCanMoveForward(): void
    {
        $this->assertEquals('Q01', $this->testSession->getCurrentAssessmentItemRef()->getIdentifier());

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $this->assertEquals('Q02', $this->testSession->getCurrentAssessmentItemRef()->getIdentifier());
    }

    public function testItCanMoveToNextSection(): void
    {
        $this->assertEquals('S01', $this->testSession->getCurrentAssessmentSection()->getIdentifier());

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_SECTION,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $this->assertEquals('S02', $this->testSession->getCurrentAssessmentSection()->getIdentifier());
    }

    public function testItCanMoveToNextPart(): void
    {
        $this->assertEquals('TP01', $this->testSession->getCurrentTestPart()->getIdentifier());
        $this->assertTrue($this->testSession->getRoute()->valid());

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_TEST_PART,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $this->assertFalse($this->testSession->getRoute()->valid());
    }

    public function testItCanNavigateToItemRef(): void
    {
        $this->subject->navigateToItemRef(
            $this->deliveryExecution,
            'Q03',
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        self::assertEquals(
            'Q03',
            $testSession->getCurrentAssessmentItemRef()->getIdentifier(),
        );
    }

    public function testItCanWorkWithTestScope(): void
    {
        $this->assertTrue($this->testSession->isRunning());

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_TEST,
            TestSessionNavigator::DIRECTION_NEXT,
        );

        $this->assertFalse($this->testSession->isRunning());
    }

    public function testItCanJumpAndMoveBackward(): void
    {
        $this->createTestSession('BasicNonLinearOneAttempt');

        $this->assertEquals('Q01', $this->testSession->getCurrentAssessmentItemRef()->getIdentifier());

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_JUMP,
            2,
        );

        $this->assertEquals('Q03', $this->testSession->getCurrentAssessmentItemRef()->getIdentifier());

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_BACK,
        );

        $this->assertEquals('Q02', $this->testSession->getCurrentAssessmentItemRef()->getIdentifier());
    }

    public function testItThrowsExceptionIfProvidedDirectionIsNotValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid move direction: somewhere');

        $this->subject->navigate($this->deliveryExecution, TestSessionNavigator::SCOPE_ITEM, 'somewhere');
    }

    public function testItThrowsExceptionIfProvidedDirectionIsNotValidForProvidedScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid move direction: previous, only "next" is supported');

        $this->subject->navigate($this->deliveryExecution, TestSessionNavigator::SCOPE_SECTION, TestSessionNavigator::DIRECTION_BACK);
    }

    public function testItThrowsExceptionIfMoveBackwardIsNotAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('It is not possible to move backward');

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_BACK,
        );
    }

    public function testItThrowsExceptionIfRefIsNotProvidedOnJump(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ref parameter');

        $this->subject->navigate(
            $this->deliveryExecution,
            TestSessionNavigator::SCOPE_ITEM,
            TestSessionNavigator::DIRECTION_JUMP,
        );
    }

    public function testItThrowsExceptionIfProvidedScopeIsNotValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scope parameter: invalid_scope');

        $this->subject->navigate($this->deliveryExecution, 'invalid_scope', TestSessionNavigator::DIRECTION_NEXT);
    }

    private function createTestSession(string $packageName = 'BasicTestFullTimerStack'): void
    {
        $this->deliveryExecution = $this->createTestDeliveryExecution(
            "userId#$packageName#resultId#tenantId",
            $packageName,
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Q01/item.json',
            'Q02/item.json',
            'Q03/item.json',
        ], $packageName);

        $this->testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);

        $this->testSession->beginTestSession();
        $this->testSession->beginAttempt();

        $this->deliveryExecutionPropertyService->persistTestSession($this->testSession);
    }
}
