<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Messenger\Handler;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Generator\UuidGenerator;
use App\Lti\Sender\LtiBasicOutcomeSender;
use App\Messenger\Handler\ItemExternalScoringHandler;
use App\Messenger\Message\DeliveryExecutionItemExternalScoringMessage;
use App\Qti\Service\AssessmentResultUpdateService;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use DateTimeInterface;
use League\Flysystem\FilesystemReader;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class ItemExternalScoringHandlerTest extends KernelTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use LoggerTestingTrait;
    use QtiTestingTrait;

    private const DELIVERY_EXECUTION_ID = 'userId#BasicWithExternalScored#resultId#tenantId';

    private ItemExternalScoringHandler $subject;
    private SerializerInterface $serializer;
    private FilesystemReader $resultStorage;
    private LtiBasicOutcomeSender $basicOutcomeSender;
    private DataStoreSenderInterface $dataStoreSender;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestDocumentManager();

        $this->serializer = self::getContainer()->get('messenger.transport.external_scoring_serializer');
        $this->resultStorage = self::getContainer()->get('delivery_execution_result.storage');
        $this->basicOutcomeSender = $this->createMock(LtiBasicOutcomeSender::class);
        $this->dataStoreSender = $this->createMock(DataStoreSenderInterface::class);

        $this->subject = new ItemExternalScoringHandler(
            static::getContainer()->get(AssessmentResultUpdateService::class),
            static::getContainer()->get(DeliveryExecutionService::class),
            static::getContainer()->get(ExtractDeliveryExecutionResultService::class),
            static::getContainer()->get(LoggerInterface::class),
            $this->basicOutcomeSender,
            static::getContainer()->get(UuidGenerator::class),
            $this->dataStoreSender,
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function testMessageProcessedCorrectly(): void
    {
        Carbon::setTestNow(
            Carbon::create(2023, 11, 1, 12, 30, 0, 'Europe/Luxembourg'),
        );
        $delivery = $this->createTestDelivery(
            id: 'BasicWithExternalScored',
            compactTestFilePath: 'BasicWithExternalScored/compact-test.xml',
        );
        $this->saveDocument($delivery);

        $deliveryExecution = $this->getDeliveryExecution(
            DeliveryExecution::STATUS_INTERACTING,
            Carbon::now(),
            Carbon::now(),
        );
        $this->saveDocument($deliveryExecution);

        $this->dataStoreSender
            ->expects(static::once())
            ->method('send');

        $this->basicOutcomeSender
            ->expects(static::once())
            ->method('send');

        $this->subject->__invoke($this->getMessageExample());

        $this->assertFalse($this->resultStorage->has($this->normalizeResultId($deliveryExecution->getId())));
        $this->assertTrue(
            $this->resultStorage->has(
                $this->normalizeResultId(
                    "{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}",
                ),
            ),
        );

        $this->assertHasLogRecordWithMessage('Result/Scoring was extracted and processed', Logger::INFO);
    }

    public function testFailIfNoDeliveryExecutionFound(): void
    {
        Carbon::setTestNow(
            Carbon::create(2023, 11, 1, 12, 30, 0, 'Europe/Luxembourg'),
        );
        $delivery = $this->createTestDelivery(
            id: 'BasicWithExternalScored',
            compactTestFilePath: 'BasicWithExternalScored/compact-test.xml',
        );
        $this->saveDocument($delivery);

        $this->basicOutcomeSender
            ->expects(static::never())
            ->method('send');

        $this->subject->__invoke($this->getMessageExample());

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicWithExternalScored#resultId#tenantId] Delivery execution was not found',
            Logger::INFO,
        );
    }

    private function getDeliveryExecution(
        string $deliveryExecutionStatus,
        ?DateTimeInterface $startedAt = null,
        ?DateTimeInterface $endedAt = null,
        array $ltiParameters = [
            'platform_issuer' => 'platformAudience',
            'client_id' => 'registrationClientId',
            'user_id' => 'userId',
            'context_id' => 'contextId',
            'result_id' => 'test_taker_result_id',
        ],
        bool $sessionStarted = true,
    ): DeliveryExecution {
        $packageName = 'BasicWithExternalScored';
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q02/item.json',
            'Item-Q03/item.json',
        ], $packageName);

        $deliveryExecution = $this->createTestDeliveryExecution(
            self::DELIVERY_EXECUTION_ID,
            'BasicWithExternalScored',
            'tenantId',
            $ltiParameters,
            '',
            new DeliveryExecutionExtraStateData(),
            $deliveryExecutionStatus,
            $startedAt,
            $endedAt,
        );

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($sessionStarted) {
            $testSession->beginTestSession();
            $testSession->beginAttempt();
        }

        $deliveryExecutionPropertyService->persistTestSession($testSession);

        return $deliveryExecution;
    }

    private function getMessageExample(): DeliveryExecutionItemExternalScoringMessage
    {
        return $this->serializer->decode(json_decode($this->getPayloadJson(), true))->getMessage();
    }

    private function getPayloadJson(): string
    {
        return file_get_contents(
            __DIR__ . '/../../../Resources/Payload/DeliveryExecutionItemExternalScoringMessage.json',
        );
    }

    private function normalizeResultId(string $resultId): string
    {
        return preg_replace('~[/\\\\]~', '_', $resultId) . '.xml';
    }
}
