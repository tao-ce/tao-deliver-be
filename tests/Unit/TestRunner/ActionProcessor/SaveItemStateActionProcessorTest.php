<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\ConcurrentProcessException;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\SaveItemStateActionProcessor;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Monolog\Logger;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SaveItemStateActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'saveItemState';

    private SaveItemStateActionProcessor $subject;
    private DeliveryExecution $deliveryExecution;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private AssessmentTestSession $testSession;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->subject = new SaveItemStateActionProcessor(
            $this->deliveryExecutionPropertyService,
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
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

        $this->testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);

        $this->testSession->beginTestSession();
        $this->testSession->beginAttempt();

        $this->deliveryExecutionPropertyService->persistTestSession($this->testSession);
    }

    public function testGetName(): void
    {
        $this->assertEquals(self::EXPECTED_ACTION_NAME, $this->subject->getActionName());
    }

    public function testItImplementsActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    /**
     * @dataProvider provideItemStates
     */
    public function testSuccessfullySavedItemState(string $itemState): void
    {
        $itemId = 'Item-Q01';
        $actionId = sprintf('%s_%s', self::EXPECTED_ACTION_NAME, random_int(1, PHP_INT_MAX));

        $processResult = $this->subject->process($this->deliveryExecution, [
            'id' => $actionId,
            'name' => self::EXPECTED_ACTION_NAME,
            'parameters' => [
                'itemIdentifier' => $itemId,
                'itemState' => $itemState,
            ],
        ]);

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - itemState: [%s] for item - [%s] was stored',
                $this->deliveryExecution->getId(),
                $itemState,
                $itemId,
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );
        self::assertEquals($itemState, $this->deliveryExecution->getExtraStateData()->getTemporaryItemState($itemId));
        self::assertNull($this->deliveryExecution->getExtraStateData()->getItemState($itemId));
        self::assertSame([
            'success' => true,
            'name' => self::EXPECTED_ACTION_NAME,
            'id' => $actionId,
            'errorCode' => null,
            'errorMessage' => null,
            'values' => [],
        ], $processResult);
    }
    /**
     * @dataProvider provideItemStates
     */
    public function testFailedSaveNotCurrentItemState(string $itemState): void
    {
        $itemId = 'Item-Q02';
        $actionId = sprintf('%s_%s', self::EXPECTED_ACTION_NAME, random_int(1, PHP_INT_MAX));

        $this->expectException(ConcurrentProcessException::class);
        $this->subject->process($this->deliveryExecution, [
            'id' => $actionId,
            'name' => self::EXPECTED_ACTION_NAME,
            'parameters' => [
                'itemIdentifier' => $itemId,
                'itemState' => $itemState,
            ],
        ]);
    }

    public function testSuccessAccessibilityValidation(): void
    {
        $this->subject->validateAvailability(DeliveryExecution::STATUS_INTERACTING);
        self::assertTrue(true);
    }

    public function testFailedAccessibilityValidationForUnavailableStatus(): void
    {
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_SUSPENDED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is suspended',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_TERMINATED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is terminated',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_CLOSED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is closed',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_INITIAL);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session in unavailable status "initial"',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
    }

    public function provideItemStates(): array
    {
        return [
            ['{}'],
            ['{\"RESPONSE\":{\"count\":{\"words\":1,\"chars\":8,\"maxCharLimitExceeded\":false},\"response\":{\"base\":{\"string\":\"Lorem ...\"}},\"validity\":true}}'],
        ];
    }
}
