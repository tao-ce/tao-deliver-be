<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\EventSubscriber\AcsControlLogSubscriber;
use App\Lti\Event\AcsControlProcessedEvent;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use Exception;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\data\AssessmentItemRef;
use qtism\runtime\storage\common\StorageException;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AcsControlLogSubscriberTest extends KernelTestCase
{
    private const EXPECTED_ACS_RESULT_STATUS = 'expectedStatus';
    private const EXPECTED_DE_ID = 'deId';
    private const EXPECTED_ITEM_ID = 'itemId';

    private AcsControlLogSubscriber $subject;

    private MessageBusInterface|MockObject $messageBusMock;
    private TestSessionAccessorFactory|MockObject $testSessionAccessorFactoryMock;

    protected function setUp(): void
    {
        $this->testSessionAccessorFactoryMock = $this->createMock(TestSessionAccessorFactory::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);

        $assessmentTestSessionFactory = $this->createMock(AssessmentTestSessionFactory::class);
        $assessmentTestSessionFactory->method('createByLtiLaunchParams')
            ->willReturnArgument(0);

        $this->subject = new AcsControlLogSubscriber(
            new DeliveryExecutionPropertyService(
                $this->testSessionAccessorFactoryMock,
                $this->getContainer()->get(LtiCustomSettings::class),
                $assessmentTestSessionFactory,
            ),
            $this->messageBusMock,
        );
    }

    public function testSuccessOnAcsControlProcessedWithRunningTestSession(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $message = new DeliveryExecutionAcsLogMessage(
            self::EXPECTED_DE_ID,
            self::EXPECTED_ITEM_ID,
            self::EXPECTED_ACS_RESULT_STATUS,
            $acsControlMock,
        );
        $this->messageBusMock
            ->expects(self::exactly(1))
            ->method('dispatch')
            ->with($message)
            ->willReturn(Envelope::wrap($message));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn(self::EXPECTED_DE_ID);
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn(self::EXPECTED_DE_ID);
        $deliveryExecutionMock
            ->method('getQtiSdkEncodedTestSession')
            ->willReturn('session_data');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->expects(self::exactly(1))
            ->method('getIdentifier')
            ->willReturn(self::EXPECTED_ITEM_ID);

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::exactly(1))
            ->method('getCurrentAssessmentItemRef')
            ->willReturn($assessmentItemRefMock);

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects(self::exactly(1))
            ->method('retrieve')
            ->with(self::EXPECTED_DE_ID)
            ->willReturn($testSessionMock);

        $this->testSessionAccessorFactoryMock
            ->expects(self::exactly(1))
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willReturn($testSessionAccessorMock);

        $this->subject->onAcsControlProcessed(
            new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ),
        );
    }

    public function testSuccessOnAcsControlProcessedWithNotRunningTestSession(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $message = new DeliveryExecutionAcsLogMessage(
            self::EXPECTED_DE_ID,
            null,
            self::EXPECTED_ACS_RESULT_STATUS,
            $acsControlMock,
        );
        $this->messageBusMock
            ->expects(self::exactly(1))
            ->method('dispatch')
            ->with($message)
            ->willReturn(Envelope::wrap($message));

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn(self::EXPECTED_DE_ID);
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn(self::EXPECTED_DE_ID);
        $deliveryExecutionMock
            ->method('getQtiSdkEncodedTestSession')
            ->willReturn('session_data');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->expects(self::never())
            ->method('getIdentifier');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::exactly(1))
            ->method('getCurrentAssessmentItemRef')
            ->willReturn(false);

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects(self::exactly(1))
            ->method('retrieve')
            ->with(self::EXPECTED_DE_ID)
            ->willReturn($testSessionMock);

        $this->testSessionAccessorFactoryMock
            ->expects(self::exactly(1))
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willReturn($testSessionAccessorMock);

        $this->subject->onAcsControlProcessed(
            new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ),
        );
    }

    public function testFailureOnAcsControlProcessedWithSessionAccessorCreationException(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $message = new DeliveryExecutionAcsLogMessage(
            self::EXPECTED_DE_ID,
            null,
            self::EXPECTED_ACS_RESULT_STATUS,
            $acsControlMock,
        );
        $this->messageBusMock
            ->expects(self::never())
            ->method('dispatch')
            ->with($message);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId');

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->expects(self::never())
            ->method('getIdentifier');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::never())
            ->method('getCurrentAssessmentItemRef');

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects(self::never())
            ->method('retrieve');

        $this->testSessionAccessorFactoryMock
            ->expects(self::exactly(1))
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willThrowException(new Exception());

        $this->expectException(Exception::class);

        $this->subject->onAcsControlProcessed(
            new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ),
        );
    }

    public function testFailureOnAcsControlProcessedWithSessionReteivingException(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $message = new DeliveryExecutionAcsLogMessage(
            self::EXPECTED_DE_ID,
            null,
            self::EXPECTED_ACS_RESULT_STATUS,
            $acsControlMock,
        );
        $this->messageBusMock
            ->expects(self::never())
            ->method('dispatch')
            ->with($message);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn(self::EXPECTED_DE_ID);
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn(self::EXPECTED_DE_ID);

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->expects(self::never())
            ->method('getIdentifier');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::never())
            ->method('getCurrentAssessmentItemRef');

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects(self::never())
            ->method('retrieve')
            ->with(self::EXPECTED_DE_ID);

        $testSessionAccessorMock
            ->expects(self::exactly(1))
            ->method('instantiate')
            ->with(0, self::EXPECTED_DE_ID)
            ->willThrowException(new StorageException());

        $this->testSessionAccessorFactoryMock
            ->expects(self::exactly(1))
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willReturn($testSessionAccessorMock);

        $this->expectException(StorageException::class);

        $this->subject->onAcsControlProcessed(
            new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ),
        );
    }
}
