<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Proctoring;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsServiceServerControlProcessor;
use App\Service\AssessmentControl\AssessmentControlProcessor;
use App\Service\AssessmentControl\Exception\NotControllableDeliveryExecutionException;
use App\Service\AssessmentControl\Exception\NotSupportedAssessmentControlAction;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AcsServiceServerControlProcessorTest extends TestCase
{
    private bool $isAcsServiceEnabled;
    private AcsServiceServerControlProcessor $subject;
    private RequestStack|MockObject $requestStackMock;
    private DeliveryExecutionServiceInterface|MockObject $deliveryExecutionServiceMock;
    private LtiCustomSettings|MockObject $ltiCustomSettingsMock;
    private AssessmentControlProcessor|MockObject $assessmentControlProcessorMock;

    protected function setUp(): void
    {
        $this->isAcsServiceEnabled = true;
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->ltiCustomSettingsMock = $this->createMock(LtiCustomSettings::class);
        $this->assessmentControlProcessorMock = $this->createMock(AssessmentControlProcessor::class);
        $featureFlagAdapter = $this->createMock(FeatureFlagAdapterInterface::class);
        $featureFlagAdapter
            ->method('isEnabled')
            ->willReturnReference($this->isAcsServiceEnabled);

        $this->subject = new AcsServiceServerControlProcessor(
            $this->requestStackMock,
            $this->deliveryExecutionServiceMock,
            $this->ltiCustomSettingsMock,
            $this->assessmentControlProcessorMock,
            $featureFlagAdapter,
        );
    }

    /**
     * @dataProvider acsActionProvider
     */
    public function testProcess(string $acsAction): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);

        $ltiResourceLinkMock = $this->createMock(LtiResourceLinkInterface::class);
        $ltiResourceLinkMock
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn('resource_link_id');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn($acsAction);

        $acsControlMock
            ->expects($this->once())
            ->method('getResourceLink')
            ->willReturn($ltiResourceLinkMock);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(true);

        $this->assessmentControlProcessorMock
            ->expects(self::once())
            ->method('__invoke')
            ->with($deliveryExecutionMock, $acsControlMock)
            ->willReturn($acsControlResultMock);

        $this->assertSame($acsControlResultMock, $this->subject->process($registrationMock, $acsControlMock));
    }

    public function testProcessWithDisabledAcsFeatureFlagAction(): void
    {
        $this->isAcsServiceEnabled = false;
        $registrationMock = $this->createMock(RegistrationInterface::class);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->once())
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(true);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Delivery execution "deliveryExecutionId" can not be controlled by ACS');

        $this->assessmentControlProcessorMock
            ->expects(self::never())
            ->method('__invoke');

        $this->subject->process($registrationMock, $acsControlMock);
    }

    public function testProcessWithUnsupportedAcsAction(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(2))
            ->method('getAction')
            ->willReturn('unsupported-action');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid ACS action provided: unsupported-action');

        $this->assessmentControlProcessorMock
            ->expects(self::never())
            ->method('__invoke');

        $this->subject->process($registrationMock, $acsControlMock);
    }

    public function testProcessWithNonExistingDeliveryExecution(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->once())
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willThrowException(new DocumentNotFoundException('reason'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('reason');

        $this->subject->process($registrationMock, $acsControlMock);
    }

    public function testProcessWhenMonitoringNotEnabled(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->once())
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(false);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Delivery execution "deliveryExecutionId" can not be controlled by ACS');

        $this->subject->process($registrationMock, $acsControlMock);
    }

    public function testProcessWhenResourceLinkIdIsEmpty(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->once())
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn([]);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with([])
            ->willReturn(true);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Incorrect resource link id has been provided');

        $this->assertSame($acsControlResultMock, $this->subject->process($registrationMock, $acsControlMock));
    }

    public function testProcessWhenResourceLinkIdsAreMismatching(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);

        $ltiResourceLinkMock = $this->createMock(LtiResourceLinkInterface::class);
        $ltiResourceLinkMock
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn('resource_link_id');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->once())
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $acsControlMock
            ->expects($this->once())
            ->method('getResourceLink')
            ->willReturn($ltiResourceLinkMock);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'mismatching_resource_link_id']);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'mismatching_resource_link_id'])
            ->willReturn(true);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Incorrect resource link id has been provided');

        $this->assertSame($acsControlResultMock, $this->subject->process($registrationMock, $acsControlMock));
    }

    public function testProcessWhenDeliveryExecutionIsClosed(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);

        $ltiResourceLinkMock = $this->createMock(LtiResourceLinkInterface::class);
        $ltiResourceLinkMock
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn('resource_link_id');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);

        $acsControlMock
            ->expects($this->once())
            ->method('getResourceLink')
            ->willReturn($ltiResourceLinkMock);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(true);

        $this->assessmentControlProcessorMock
            ->expects(self::once())
            ->method('__invoke')
            ->with($deliveryExecutionMock, $acsControlMock)
            ->willThrowException(
                new NotControllableDeliveryExecutionException('Delivery execution\'s state does not permit this action'),
            );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Delivery execution\'s state does not permit this action');

        $this->assertSame($acsControlResultMock, $this->subject->process($registrationMock, $acsControlMock));
    }

    public function testProcessResumeWhenDeliveryExecutionIsClosed(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);

        $ltiResourceLinkMock = $this->createMock(LtiResourceLinkInterface::class);
        $ltiResourceLinkMock
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn('resource_link_id');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);
        $acsControlMock
            ->expects($this->once())
            ->method('getResourceLink')
            ->willReturn($ltiResourceLinkMock);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(true);

        $this->assessmentControlProcessorMock
            ->expects(self::once())
            ->method('__invoke')
            ->with($deliveryExecutionMock, $acsControlMock)
            ->willReturn($acsControlResultMock);

        $this->assertSame($acsControlResultMock, $this->subject->process($registrationMock, $acsControlMock));
    }

    public function testProcessWithoutSupportedProcessors(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);

        $ltiResourceLinkMock = $this->createMock(LtiResourceLinkInterface::class);
        $ltiResourceLinkMock
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn('resource_link_id');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $acsControlMock
            ->expects($this->once())
            ->method('getResourceLink')
            ->willReturn($ltiResourceLinkMock);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(true);

        $this->assessmentControlProcessorMock
            ->expects(self::once())
            ->method('__invoke')
            ->with($deliveryExecutionMock, $acsControlMock)
            ->willThrowException(
                new NotSupportedAssessmentControlAction('"resume" ACS action is not supported'),
            );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('"resume" ACS action is not supported');

        $this->subject->process($registrationMock, $acsControlMock);
    }

    public function testProcessFlagWhenDeliveryExecutionIsClosed(): void
    {
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);

        $ltiResourceLinkMock = $this->createMock(LtiResourceLinkInterface::class);
        $ltiResourceLinkMock
            ->expects($this->once())
            ->method('getIdentifier')
            ->willReturn('resource_link_id');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_FLAG);
        $acsControlMock
            ->expects($this->once())
            ->method('getResourceLink')
            ->willReturn($ltiResourceLinkMock);

        $this->requestStackMock
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(new Request([], [], ['_route_params' => ['deliveryExecutionId' => 'deliveryExecutionId']]));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['resource_link_id' => 'resource_link_id']);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->willReturn($deliveryExecutionMock);

        $this->ltiCustomSettingsMock
            ->expects($this->once())
            ->method('isMonitoringEnabled')
            ->with(['resource_link_id' => 'resource_link_id'])
            ->willReturn(true);

        $this->assessmentControlProcessorMock
            ->expects(self::once())
            ->method('__invoke')
            ->with($deliveryExecutionMock, $acsControlMock)
            ->willReturn($acsControlResultMock);

        $this->assertSame($acsControlResultMock, $this->subject->process($registrationMock, $acsControlMock));
    }

    public function acsActionProvider(): array
    {
        return array_map(static function (string $acsAction) {
            return [$acsAction];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }
}
