<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Repository\DeliveryRepository;
use App\TestRunner\ActionProcessor\GetNextItemDataActionProcessor;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GetNextItemDataActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use FilesystemTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'getNextItemData';

    private SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry;
    private GetNextItemDataActionProcessor $subject;


    private DeliveryRepository $deliveryRepositoryMock;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->signedUrlGeneratorRegistry = static::getContainer()->get(SignedUrlGeneratorRegistry::class);

        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        static::getContainer()->set(DeliveryRepository::class, $this->deliveryRepositoryMock);

        $this->subject = static::getContainer()->get(GetNextItemDataActionProcessor::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals(GetNextItemDataActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testProcess(): void
    {
        Carbon::setTestNow(Carbon::now());

        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml', 'Item-Q01/item.json', 'Item-Q01/portableElements.json',
                'Item-Q02/item.json', 'Item-Q02/portableElements.json',
                'Item-Q03/item.json', 'Item-Q03/portableElements.json',
            ],
            'BasicAssets',
        );

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicAssets#resultId#tenantId',
            'BasicAssets',
            'tenantId',
            ['ltiLaunchParameters'],
        );

        $itemIdentifiers = ['Item-Q01', 'Item-Q02', 'Item-Q03'];

        $response = $this->subject->process(
            $deliveryExecution,
            [
                'name' => 'getNextItemData',
                'id' => 'getNextItemData_1',
                'parameters' => [
                    'itemIdentifier' => $itemIdentifiers,
                ],
            ],
        );

        $this->assertTrue($response['success']);
        $this->assertEquals('getNextItemData', $response['name']);
        $this->assertEquals('getNextItemData_1', $response['id']);

        $this->assertArrayHasKey('values', $response);
        $this->assertArrayHasKey('items', $response['values']);

        $items = $response['values']['items'];

        $this->assertCount(3, $items);

        foreach ($items as $index => $item) {
            $this->assertEquals('', $item['baseUrl']);
            $this->assertEquals($itemIdentifiers[$index], $item['itemIdentifier']);

            $this->assertArrayHasKey('itemData', $item);
            $this->assertArrayHasKey('data', $item['itemData']);
            $this->assertArrayHasKey('assets', $item['itemData']);
            $this->assertArrayHasKey('type', $item['itemData']);

            foreach ($item['itemData']['assets'] as $type => $file) {
                foreach ($file as $name => $url) {
                    $this->assertAssetUrl($deliveryExecution->getDeliveryId(), $item['itemIdentifier'], $name, $url);
                }
            }
        }

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId] - test taker has requested the following next Item[s]: ["Item-Q01","Item-Q02","Item-Q03"]',
            Logger::INFO,
            'audit_delivery_execution',
        );
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
            $this->subject->validateAvailability(DeliveryExecution::STATUS_CLOSED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is closed',
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

    private function assertAssetUrl(string $deliveryId, string $itemIdentifier, string $name, string $url): void
    {
        $path = $this->buildPathFor($deliveryId, $itemIdentifier, $name);
        $this->assertEquals(
            $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl($path),
            $url,
        );
    }
}
