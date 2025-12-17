<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Messenger\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Handler\CleanUpHandler;
use App\Messenger\Message\QtiClassValueCleanUpMessage;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\TestRunner\Service\ExternalTimerService;
use App\Tests\Traits\CacheTestingTrait;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\ExternalTimerDefinitionTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use JsonException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CleanUpHandlerTest extends KernelTestCase
{
    use LoggerTestingTrait;
    use DocumentTestingTrait;
    use MessengerTestingTrait;
    use DomainTestingTrait;
    use FilesystemTrait;
    use QtiTestingTrait;
    use DomainTestingTrait;
    use ExternalTimerDefinitionTestingTrait;
    use CacheTestingTrait;

    private CleanUpHandler $subject;
    private string $storageDir = __DIR__ . '/../../../Resources/Qti/CompiledPackages';
    private string $deliveryExecutionId = 'userId#ExtendedTextInteraction#resultId#tenantId';

    /**
     * @throws JsonException
     */
    public function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestDocumentManager();
        $this->setUpTestMessageBus();
        $this->setUpTestCache();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function testProcessFailedDeliveryExecution()
    {
        Carbon::setTestNow(Carbon::now('Europe/Luxembourg'));

        $this->subject = $this->createBasicSubject();
        $message = new QtiClassValueCleanUpMessage('invalidId');

        $this->subject->__invoke($message);

        $this->assertHasLogRecordWithMessage("with id 'invalidId' not found", Logger::WARNING);
    }

    public function testRemoveHistoryFromState(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'item-1/item.json',
            'item-2/item.json',
            'item-3/item.json',
        ], 'ExtendedTextInteraction');

        $deliveryExecution = $this->createTestDeliveryExecution(
            $this->deliveryExecutionId,
            'ExtendedTextInteraction',
            'tenantId',
            ['user_id' => 'userId'],
            null,
            null,
            DeliveryExecution::STATUS_CLOSED,
        );
        $deliveryExecution->addItemState('item-1', $this->getItemState());

        $this->saveDocument($deliveryExecution);

        $deliveryExecutionService = $this->createMock(DeliveryExecutionServiceInterface::class);
        $deliveryExecutionService->expects($this->once())->method('saveDeliveryExecution');

        $timeService = $this->createMock(ExternalTimerService::class);
        $subject = new CleanUpHandler(
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            static::getContainer()->get(LoggerInterface::class),
            $timeService,
            $deliveryExecutionService,
        );

        $message = new QtiClassValueCleanUpMessage($this->deliveryExecutionId);

        $subject->__invoke($message);
    }

    public function testIncorrectItemStateStaySilent(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'item-1/item.json',
            'item-2/item.json',
            'item-3/item.json',
        ], 'ExtendedTextInteraction');

        $deliveryExecution = $this->createTestDeliveryExecution(
            $this->deliveryExecutionId,
            'ExtendedTextInteraction',
            'tenantId',
            ['user_id' => 'userId'],
            null,
            null,
            DeliveryExecution::STATUS_CLOSED,
        );
        $deliveryExecution->addItemState('item-1', '{');

        $this->saveDocument($deliveryExecution);

        $deliveryExecutionService = $this->createMock(DeliveryExecutionServiceInterface::class);
        $deliveryExecutionService->expects($this->never())->method('saveDeliveryExecution');

        $timeService = $this->createMock(ExternalTimerService::class);
        $subject = new CleanUpHandler(
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            static::getContainer()->get(LoggerInterface::class),
            $timeService,
            $deliveryExecutionService,
        );

        $message = new QtiClassValueCleanUpMessage($this->deliveryExecutionId);

        $subject->__invoke($message);
    }

    private function createBasicSubject(): CleanUpHandler
    {
        $this->subject = new CleanUpHandler(
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(ExternalTimerService::class),
            static::getContainer()->get(DeliveryExecutionServiceInterface::class),
        );

        return $this->subject;
    }

    private function getItemState(): string
    {
        return '{
  "RESPONSE": {
    "response": {
      "base": {
        "string": "<final_response>"
      }
    },
    "history": [
      {
        "response": {
          "base": {
            "string": "<draft_response_1>"
          }
        }
      },
      {
        "response": {
          "base": {
            "string": "<draft_response_2>"
          }
        }
      }
    ],
    "validity": true,
    "count": {
      "words": 1,
      "chars": 16,
      "maxCharLimitExceeded": false
    }
  },
  "touched": false
}';
    }
}
