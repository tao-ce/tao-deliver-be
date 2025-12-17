<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\EventSubscriber\ScoringEventSubscriber;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\DeliveryExecutionScoredEvent;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use Monolog\Logger;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryExecutionFinishedEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScoringEventSubscriberTest extends KernelTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;
    use MessengerTestingTrait;
    use QtiTestingTrait;

    /** @var ScoringEventSubscriber */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        Carbon::setTestNow(Carbon::createFromTimestamp(1597248430));

        $this->setUpTestLogHandler();
        $this->setUpTestMessageBus();

        $this->subject = static::getContainer()->get(ScoringEventSubscriber::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testOnTestSessionEnded(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q02/item.json',
            'Item-Q03/item.json',
        ]);

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['user_id' => 'userId'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $deliveryExecutionPropertyService->persistTestSession($testSession);

        $event = new DeliveryExecutionScoredEvent($deliveryExecution);

        $this->subject->onDeliveryExecutionScored($event);

        $this->assertCountTransportMessages('scoring-submission', 1);
        $this->assertHasTransportMessage('scoring-submission', DeliveryExecutionFinishedEvent::class);

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] Successfully dispatched DeliveryExecutionFinishedEvent',
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testItDoesNotSendForAnonymous(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'item-1/item.json',
            'item-2/item.json',
            'item-3/item.json',
        ], 'BasicHumanScorable');

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicHumanScorable#resultId#tenantId',
            'BasicHumanScorable',
            'tenantId',
            ['user_id' => 'anonymous-xxx'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $deliveryExecutionPropertyService->persistTestSession($testSession);

        $event = new DeliveryExecutionScoredEvent($deliveryExecution);

        $this->subject->onDeliveryExecutionScored($event);

        $this->assertCountTransportMessages('scoring-submission', 0);

        $this->assertHasNoLogRecordWithMessage(
            '[userId#BasicHumanScorable#resultId#tenantId] Scoring submission message',
            Logger::INFO,
        );
    }
}
