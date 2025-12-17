<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DataStore\Sender;

use App\DataStore\Sender\DataStoreResultsSender;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DataStoreResultMessage;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Enrollment\EnrollmentService;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use App\Tests\Traits\DataStoreTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use Carbon\Carbon;
use Exception;
use JsonSchema\Validator;
use Monolog\Logger;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\runtime\storage\common\StorageException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DataStoreResultsSenderTest extends KernelTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;
    use DataStoreTestingTrait {
        getPayloadArray as getPayload;
    }

    private const DATA_STORE_JSON_SCHEMA = __DIR__ . '/../../../Resources/DataStore/data-store-schema.json';

    private DataStoreResultsSender $subject;
    private TestSessionAccessorFactory&MockObject $testSessionAccessorFactoryMock;
    private MessageBusInterface $messageBusMock;
    private EnrollmentService $enrollmentService;
    private bool $isItemStatePersistenceEnabled = false;

    protected function setUp(): void
    {
        self::bootKernel();

        Carbon::setTestNow(Carbon::now());

        $this->setUpTestLogHandler();

        $this->testSessionAccessorFactoryMock = $this->getTestSessionAccessorFactoryMock();
        $this->messageBusMock = $this->getMessageBusMock();
        $this->enrollmentService = $this->createMock(EnrollmentService::class);
        $this->enrollmentService
            ->method('getSessionDataByDeliveryExecution')
            ->willReturn([]);
        $featureFlagAdapter = $this->createMock(FeatureFlagAdapterInterface::class);
        $featureFlagAdapter
            ->method('isEnabled')
            ->willReturnCallback(fn(string $_, string $flag) => match ($flag) {
                'PERSIST_ITEM_STATE_IN_DATA_STORE' => $this->isItemStatePersistenceEnabled,
                default => false,
            });

        $this->subject = new DataStoreResultsSender(
            $featureFlagAdapter,
            new DeliveryExecutionPropertyService(
                $this->testSessionAccessorFactoryMock,
                static::getContainer()->get(LtiCustomSettings::class),
                static::getContainer()->get(AssessmentTestSessionFactory::class),
            ),
            $this->createExtractDeliveryExecutionResultServiceMock(),
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(NormalizerInterface::class),
            $this->messageBusMock,
            $this->enrollmentService,
        );
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testIfTestSessionNotFound(): void
    {
        $this->testSessionAccessorFactoryMock->method('create')->willThrowException(new StorageException());

        $this->expectException(StorageException::class);

        $this->subject->send(
            $this->getDeliveryExecution([]),
        );
    }

    public function testIfInvalidLtiParameters(): void
    {
        $ltiParameters = $this->getLtiParameters();
        unset($ltiParameters['user_id']);

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope($this->getResultMessage()));

        $this->subject->send(
            $this->getDeliveryExecution($ltiParameters),
        );
    }

    public function testSendValidatePayloadSchema(): void
    {
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (DataStoreResultMessage $message): bool {
                $payload = json_decode(json_encode($message->getDeliveryResult()));
                $validator = new Validator();
                $validator->validate(
                    $payload,
                    (object)['$ref' => 'file://' . realpath(self::DATA_STORE_JSON_SCHEMA)],
                );
                foreach ($validator->getErrors() as $error) {
                    printf("[%s] %s\n", $error['property'], $error['message']);
                }
                self::expectOutputString('');
                return true;
            }))
            ->willReturn(new Envelope($this->getResultMessage()));

        $this->subject->send(
            $this->getDeliveryExecution($this->getLtiParameters()),
        );
    }

    /**
     * @runInSeparateProcess
     * @dataProvider featureFlagStateDataProvider
     */
    public function testSend(bool $isItemStatePersistenceEnabled): void
    {
        $this->isItemStatePersistenceEnabled = $isItemStatePersistenceEnabled;
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($this->getResultMessage())
            ->willReturn(new Envelope($this->getResultMessage()));

        $this->subject->send(
            $this->getDeliveryExecution($this->getLtiParameters()),
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] The message was successfully sent to Data Store Result Queue.',
            Logger::INFO,
        );
    }

    /**
     * @runInSeparateProcess
     * @dataProvider featureFlagStateDataProvider
     */
    public function testManualScoreItemUpdated(bool $isItemStatePersistenceEnabled): void
    {
        $this->isItemStatePersistenceEnabled = $isItemStatePersistenceEnabled;
        $testItemId = 'item2';
        $resultMessage = $this->getResultMessageWithManualScored($testItemId);

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($resultMessage)
            ->willReturn(new Envelope($resultMessage));

        $deliveryExecution = $this->getDeliveryExecution($this->getLtiParameters())
            ->withFinalManuallyGradedItem($testItemId, Carbon::now());
        $this->subject->send($deliveryExecution);

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] The message was successfully sent to Data Store Result Queue.',
            Logger::INFO,
        );
    }

    public function testFailedMessageSent(): void
    {
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new Exception());

        $this->expectException(Exception::class);

        $this->subject->send(
            $this->getDeliveryExecution($this->getLtiParameters()),
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] Failed to publish the message to Data Store Result Queue.',
            Logger::ALERT,
        );
    }

    public function featureFlagStateDataProvider(): array
    {
        return [
            'enabled' => [true],
            'disabled' => [false],
        ];
    }

    protected function getPayloadArray(): array
    {
        $payload = $this->getPayload();
        if (!$this->isItemStatePersistenceEnabled) {
            array_walk(
                $payload['assessmentResult']['itemResults'],
                static function (&$itemResults): void {
                    foreach ($itemResults as &$itemResult) {
                        $itemResult['state'] = null;
                    }
                },
            );
        }

        return $payload;
    }
}
