<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Action\Security\Battery;

use App\Action\Security\Battery\BatteryContinueAction;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\Lti\LtiLaunchService;
use App\TestRunner\Service\BatteryNavigationService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class BatteryContinueActionTest extends TestCase
{
    use DomainTestingTrait;
    use OAuth2SecurityTestingTrait;

    private readonly BatteryContinueAction $subject;
    private readonly BatteryNavigationService $batteryNavigationService;
    private readonly LtiLaunchService $ltiLaunchService;
    private readonly DeliveryExecutionServiceInterface $deliveryExecutionService;
    private readonly LoggerInterface $logger;
    private readonly DeliveryExecution|MockObject $deliveryExecution;
    private readonly DeliveryExecution|MockObject $nextDeliveryExecution;

    protected function setUp(): void
    {
        $this->batteryNavigationService = $this->createMock(BatteryNavigationService::class);
        $this->ltiLaunchService = $this->createMock(LtiLaunchService::class);
        $this->deliveryExecutionService =  $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new BatteryContinueAction(
            $this->batteryNavigationService,
            $this->ltiLaunchService,
            $this->deliveryExecutionService,
            $this->logger,
        );

        $this->deliveryExecution = $this->createTestDeliveryExecution();
        $this->nextDeliveryExecution = $this->createTestDeliveryExecution(
            'userId#nextDelivery#resultId#tenantId',
            'nextDeliveryId',
            ltiLaunchParameters: ['id_token' => $this->createOAuth2AccessToken('userId#nextDelivery#resultId#tenantId')],
        );
        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->with($this->deliveryExecution->getId())
            ->willReturn($this->deliveryExecution);

        $batteryDistribution = $this->createMock(BatteryDistribution::class);
        $this->batteryNavigationService
            ->expects($this->once())
            ->method('getBatteryDistribution')
            ->with($this->deliveryExecution)
            ->willReturn($batteryDistribution);
        $this->batteryNavigationService
            ->expects($this->once())
            ->method('getNextDeliveryExecution')
            ->with($this->deliveryExecution, $batteryDistribution)
            ->willReturn($this->nextDeliveryExecution);
    }

    public function testInvoke(): void
    {
        $response = new Response();
        $this->ltiLaunchService
            ->expects($this->never())
            ->method('requireAuthorization');
        $this->ltiLaunchService
            ->expects($this->once())
            ->method('launchTest')
            ->with($this->nextDeliveryExecution, $this->nextDeliveryExecution->getLtiLaunchParameters())
            ->willReturn($response);

        $this->assertSame($response, ($this->subject)($this->deliveryExecution->getId()));
    }
}
