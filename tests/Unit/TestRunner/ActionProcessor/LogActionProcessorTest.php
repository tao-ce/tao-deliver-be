<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\DeliveryExecutionUIEventMessage;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\LogActionProcessor;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Validator\Exception\RequestValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LogActionProcessorTest extends KernelTestCase
{
    use MessengerTestingTrait;
    use DocumentTestingTrait;
    use DomainTestingTrait;

    private const EXPECTED_ACTION_NAME = 'ui-log';
    private const TRANSPORT_NAME = 'delivery-execution-ui-events';
    private const DELIVERY_EXECUTION_ID = 'userId#deliveryId#resultId#tenantId';

    private DeliveryExecution $deliveryExecution;
    private LogActionProcessor $subject;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestDocumentManager();
        $this->setUpTestMessageBus();

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            self::DELIVERY_EXECUTION_ID,
            'deliveryId',
            'tenantId',
            [],
            'lis',
        );
        $this->saveDocument($this->deliveryExecution);

        $this->subject = static::getContainer()->get(LogActionProcessor::class);
    }

    public function testItSkipExtraInput(): void
    {
        $params = $this->getParams();
        $params['events'][0]['test'] = 'test';

        $this->expectException(RequestValidationException::class);
        $this->expectExceptionMessage('[0][test]: This field was not expected.');

        $this->subject->process($this->deliveryExecution, ['parameters' => $params]);
    }

    public function testValidateIncorrectInput(): void
    {
        $this->expectException(RequestValidationException::class);
        $this->expectExceptionMessage('[events]: This value should not be blank.');

        $this->subject->process($this->deliveryExecution, ['parameters' => ['events' => []]]);

        $this->expectException(RequestValidationException::class);
        $this->expectExceptionMessage('[events]: This value should not be blank.');

        $this->subject->process($this->deliveryExecution, ['parameters' => []]);
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

    public function testMessageWasPosted(): void
    {
        $this->subject->process($this->deliveryExecution, [
            'id' => 'test',
            'name' => 'log',
            'parameters' => $this->getParams(),
        ]);

        $responseEvents = [
            [
                "domEventType" => "feedtrace",
                "itemId" => "item2",
                "responseId" => "RESPONSE",
                'timestamp' => 1670595494597,
                "metadata" => [
                    "moduleId" => "platform",
                    "unitId" => "S131-GoodVibrations",
                    "itemName" => "item2",
                    "itemId" => "CS131Q04",
                    "target" => "MODULE",
                    "event_name" => "QuestionLoaded",
                    "timeStamp" => 1670595494597,
                ],
            ],
            [
                "domEventType" => "feedtrace",
                "itemId" => null,
                "responseId" => null,
                'timestamp' => 1670595494597,
                "metadata" => [
                    "moduleId" => "platform",
                    "unitId" => "S131-GoodVibrations",
                    "itemName" => "item2",
                    "itemId" => "CS131Q04",
                    "target" => "MODULE",
                    "event_name" => "QuestionLoaded",
                    "timeStamp" => 1670595494597,
                ],
            ],
        ];

        $this->assertHasTransportMessage(self::TRANSPORT_NAME, DeliveryExecutionUIEventMessage::class);

        /** @var DeliveryExecutionUIEventMessage $logMessage */
        $logMessage = current($this->getTransportMessages(self::TRANSPORT_NAME))->getMessage();

        $this->assertEquals(self::DELIVERY_EXECUTION_ID, $logMessage->getDeliveryExecutionId());
        $this->assertEquals('tenantId', $logMessage->getTenantId());
        $this->assertEquals('deliveryId', $logMessage->getDeliveryId());
        $this->assertNull($logMessage->getBatteryId());
        $this->assertEquals($responseEvents, $logMessage->getEvents());
    }

    private function getParams(): array
    {
        return [
            "events" => [
                [
                    "domEventType" => "feedtrace",
                    "itemIdentifier" => "item2",
                    "responseIdentifier" => "RESPONSE",
                    "metadata" => [
                        "moduleId" => "platform",
                        "unitId" => "S131-GoodVibrations",
                        "itemName" => "item2",
                        "itemId" => "CS131Q04",
                        "target" => "MODULE",
                        "event_name" => "QuestionLoaded",
                        "timeStamp" => 1670595494597,
                    ],
                ],
                [
                    "domEventType" => "feedtrace",
                    "itemIdentifier" => null,
                    "responseIdentifier" => null,
                    "metadata" => [
                        "moduleId" => "platform",
                        "unitId" => "S131-GoodVibrations",
                        "itemName" => "item2",
                        "itemId" => "CS131Q04",
                        "target" => "MODULE",
                        "event_name" => "QuestionLoaded",
                        "timeStamp" => 1670595494597,
                    ],
                ],
            ],

        ];
    }
}
