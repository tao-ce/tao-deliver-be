<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\OfflineDeliveryExecutionEncryptionService;
use App\Service\Encryption\Contract\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use qtism\common\datatypes\QtiFloat;
use qtism\common\datatypes\QtiIdentifier;
use qtism\common\datatypes\QtiString;
use qtism\common\enums\BaseType;
use qtism\data\AssessmentItemRef;
use qtism\runtime\common\MultipleContainer;
use qtism\runtime\common\RecordContainer;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\common\State;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionStore;
use qtism\runtime\tests\AssessmentTestSession;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\AssessmentItemSessionCollection;

class OfflineDeliveryExecutionEncryptionServiceTest extends TestCase
{
    private const ENCRYPTION_KEY = 'encryption-key';

    private OfflineDeliveryExecutionEncryptionService $subject;

    private DeliveryExecutionPropertyService $deliveryExecutionPropertyServiceMock;
    private EncryptorInterface $encryptorMock;
    private LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        $this->deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject = new OfflineDeliveryExecutionEncryptionService(
            $this->deliveryExecutionPropertyServiceMock,
            $this->encryptorMock,
            $this->loggerMock,
        );
    }

    public function testEncrypt()
    {
        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $assessmentSessionMock = $this->createMock(AssessmentTestSession::class);


        $this->encryptorMock
            ->expects($this->once())
            ->method('setEncryptionKey')
            ->with(self::ENCRYPTION_KEY);

        $assessmentItemSessionStoreMock = $this->createMock(AssessmentItemSessionStore::class);

        $responseVariableMock = $this->createMock(ResponseVariable::class);
        $qtiString1 = new QtiString('value1');
        $qtiString2 = new QtiString('value2');
        $qtiString3 = new QtiString('value3');
        $qtiString4 = new QtiString('value4');
        $qtiString5 = new QtiString('value5');
        $qtiIdentifier = new QtiIdentifier('identifier');
        $responseVariableMock
            ->expects(self::exactly(5))
            ->method('getValue')
            ->willReturnOnConsecutiveCalls(
                $qtiString1,
                $qtiString2,
                $qtiString3,
                new RecordContainer([
                    'record1' => $qtiString4,
                    'record2' => new QtiFloat(1.1),
                    'record3' => $qtiIdentifier,
                ]),
                new MultipleContainer(BaseType::STRING, [$qtiString5]),
            );
        $responseVariableMock
            ->expects(self::exactly(5))
            ->method('getIdentifier')
            ->willReturnOnConsecutiveCalls(
                'identifier1',
                'identifier2',
                'identifier3',
                'identifier4',
                'identifier5',
            );

        $assessmentItemSession1Mock = $this->createMock(AssessmentItemSession::class);
        $assessmentItemSession1Mock->expects(self::once())
            ->method('getResponseVariables')
            ->with(false)
            ->willReturn(new State([$responseVariableMock]));

        $assessmentItemSession2Mock = $this->createMock(AssessmentItemSession::class);
        $assessmentItemSession2Mock->expects(self::once())
            ->method('getResponseVariables')
            ->with(false)
            ->willReturn(new State([$responseVariableMock, $responseVariableMock]));

        $assessmentItemSession3Mock = $this->createMock(AssessmentItemSession::class);
        $assessmentItemSession3Mock->expects(self::once())
            ->method('getResponseVariables')
            ->with(false)
            ->willReturn(new State([$responseVariableMock, $responseVariableMock]));


        $itemSessionCollectionMock = new AssessmentItemSessionCollection(
            [
                $assessmentItemSession1Mock,
                $assessmentItemSession2Mock,
                $assessmentItemSession3Mock,
            ],
        );

        $assessmentItemSessionStoreMock
            ->expects($this->once())
            ->method('getAllAssessmentItemSessions')
            ->willReturn($itemSessionCollectionMock);

        $assessmentSessionMock
            ->expects($this->once())
            ->method('getAssessmentItemSessionStore')
            ->willReturn($assessmentItemSessionStoreMock);

        $deliveryExecutionMock
            ->expects(self::once())
            ->method('clearAllItemState')
            ->willReturn($deliveryExecutionMock);

        $this->deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('fetchTestSession')
            ->with($deliveryExecutionMock)
            ->willReturn($assessmentSessionMock);

        $this->deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('persistTestSession')
            ->with($assessmentSessionMock);

        $this->encryptorMock
            ->expects(self::exactly(6))
            ->method('encrypt')
            ->withConsecutive(
                ['value1'],
                ['value2'],
                ['value3'],
                ['value4'],
                ['identifier'],
                ['value5'],
            )->willReturnOnConsecutiveCalls(
                'encryptedValue1',
                'encryptedValue2',
                'encryptedValue3',
                'encryptedValue4',
                'encryptedIdentifier',
                'encryptedValue5',
            );
        $result = $this->subject->encrypt($deliveryExecutionMock, self::ENCRYPTION_KEY);
        self::assertSame($deliveryExecutionMock, $result);
        self::assertEquals(base64_encode('encryptedValue1'), $qtiString1->getValue());
        self::assertEquals(base64_encode('encryptedValue2'), $qtiString2->getValue());
        self::assertEquals(base64_encode('encryptedValue3'), $qtiString3->getValue());
        self::assertEquals(base64_encode('encryptedValue4'), $qtiString4->getValue());
        self::assertEquals(base64_encode('encryptedValue5'), $qtiString5->getValue());
        self::assertEquals(base64_encode('encryptedIdentifier'), $qtiIdentifier->getValue());
    }
}
