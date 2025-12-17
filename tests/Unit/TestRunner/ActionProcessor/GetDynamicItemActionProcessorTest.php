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
use App\TestRunner\ActionProcessor\GetItemDynamicActionProcessor;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Traits\FilesystemTrait;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GetDynamicItemActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use FilesystemTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'getItemDynamic';

    private GetItemDynamicActionProcessor $subject;
    private SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->signedUrlGeneratorRegistry = static::getContainer()->get(SignedUrlGeneratorRegistry::class);

        $this->subject = static::getContainer()->get(GetItemDynamicActionProcessor::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals(GetItemDynamicActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testProcess(): void
    {
        $this->copyCompiledTestToStorage(['compact-test.xml', 'Item-Q02/item.json', 'Item-Q02/portableElements.json'], 'BasicAssets');

        $deliveryExecution = $this->createTestDeliveryExecution(
            deliveryId: 'BasicAssets',
            ltiLaunchParameters: ['ltiLaunchParameters'],
        );

        $response = $this->subject->process(
            $deliveryExecution,
            [
                'name' => 'getDynamicItem',
                'id' => 'getDynamicItem_1',
                'parameters' => [
                    'itemIdentifier' => 'Item-Q02',
                ],
            ],
        );

        $this->assertTrue($response['success']);
        $this->assertEquals('getDynamicItem', $response['name']);
        $this->assertEquals('getDynamicItem_1', $response['id']);

        $this->assertArrayHasKey('values', $response);
        $this->assertEquals('', $response['values']['baseUrl']);
        $this->assertEquals('', $response['values']['itemState']);
        $this->assertEquals('Item-Q02', $response['values']['itemIdentifier']);

        $this->assertHasLogRecordWithMessage(
            "[{$deliveryExecution->getId()}] - test taker has requested the following Item: Item-Q02",
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
        $this->assertEquals($this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl($path), $url);
    }
}
