<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\TestRunner\Service\DeliveryExecutionClosureService;
use App\TestRunner\Service\TestSessionInitiator;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Monolog\Logger;

class DeliveryExecutionClosureServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    /** @var TestSessionAccessor */
    private $testSessionAccessor;

    /** @var DeliveryExecution */
    private $deliveryExecution;

    /** @var RepositoryAwareDeliveryExecutionServiceInterface */
    private $loggerAwareDeliveryExecutionService;

    /** @var TestSessionInitiator */
    private $testSessionInitiator;

    /** @var DeliveryExecutionClosureService */
    private $subject;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->subject = static::getContainer()->get(DeliveryExecutionClosureService::class);

        $this->loggerAwareDeliveryExecutionService = static::getContainer()->get(DeliveryExecutionService::class);

        $this->copyCompiledTestToStorage();

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );

        $this->deliveryExecution->setDeliveryPublicationTime(Carbon::parse('2020-01-01 00:00:00'));

        $this->testSessionInitiator = static::getContainer()->get(TestSessionInitiator::class);

        $this->testSessionInitiator->init($this->deliveryExecution);

        $this->loggerAwareDeliveryExecutionService->saveDeliveryExecution($this->deliveryExecution);

        $this->setUpTestDocumentManager();
        $this->saveDocument($this->createTestDelivery('Basic'));
    }

    public function testItCanClose(): void
    {
        self::assertTrue($this->subject->close($this->deliveryExecution));

        $deliveryExecution = $this->loggerAwareDeliveryExecutionService->findDeliveryExecution($this->deliveryExecution->getId());

        $this->assertNotNull($deliveryExecution->getFinishedAt());
        $this->assertEquals(DeliveryExecution::STATUS_CLOSED, $deliveryExecution->getStatus());

        $this->assertHasLogRecordWithMessage(
            'Delivery execution has been finished automatically due to scheduled closure claim is provided',
            Logger::INFO,
            'audit_platform',
        );
    }

    public function testItDoesNothingIfDeliveryExecutionIsAlreadyClosed(): void
    {
        $deliveryExecution = $this->loggerAwareDeliveryExecutionService->findDeliveryExecution($this->deliveryExecution->getId());

        $deliveryExecution->setStatus(DeliveryExecution::STATUS_CLOSED);

        $this->loggerAwareDeliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        self::assertFalse($this->subject->close($deliveryExecution));

        $this->assertHasLogRecordWithMessage(
            'Delivery execution\'s state does not permit result processing',
            Logger::INFO,
            'audit_platform',
        );
    }
}
