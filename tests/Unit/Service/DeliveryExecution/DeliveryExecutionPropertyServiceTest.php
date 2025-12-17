<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\MemoryLeaksTrait;
use App\Tests\Traits\QtiTestingTrait;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DeliveryExecutionPropertyServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use MemoryLeaksTrait;

    private const QTI_TEST_TITLE = 'Basic Test (Linear-Individual)';

    private readonly TestSessionAccessorFactory $testSessionAccessorFactory;
    private readonly DeliveryExecutionPropertyService $sut;

    /**
     * @before
     */
    public function init(): void
    {
        $this->copyCompiledTestToStorage();

        $this->testSessionAccessorFactory = static::getContainer()->get(TestSessionAccessorFactory::class);
        $this->sut = new DeliveryExecutionPropertyService(
            $this->testSessionAccessorFactory,
            static::getContainer()->get(LtiCustomSettings::class),
            static::getContainer()->get(AssessmentTestSessionFactory::class),
        );
    }

    public function testGetAllItemCategories(): void
    {
        $this->copyCompiledTestToStorage(packageName: 'BasicWithTextToSpeechCategory');
        $this->assertContains(
            'x-tao-option-tts',
            $this->sut->getAllItemCategories(
                $this->createTestDeliveryExecution(
                    'userId#BasicWithTextToSpeechCategory#resultId#tenantId',
                    'BasicWithTextToSpeechCategory',
                    testSession: '',
                ),
            ),
        );
    }

    /**
     * @dataProvider titleDataProvider
     */
    public function testGetTitle(string $expected, DeliveryExecution $deliveryExecution): void
    {
        static::assertSame($expected, $this->sut->getTestTitle($deliveryExecution));
        static::assertSame(self::QTI_TEST_TITLE, $this->sut->getQtiTestTitle($deliveryExecution));
    }

    public function testAssessmentSessionInitialization(): void
    {
        $deliveryExecution = $this->createDeliveryExecution();

        $this->sut->persistTestSession(
            $this->sut->fetchTestSession($deliveryExecution),
        );
        static::assertNotEmpty($deliveryExecution->getQtiSdkEncodedTestSession());
    }

    public function testAssessmentSessionFetching(): void
    {
        $deliveryExecution = $this->createDeliveryExecution();
        $testSessionAccessor = $this->testSessionAccessorFactory->create($deliveryExecution);
        $testSession = $testSessionAccessor->instantiate();
        $testSessionAccessor->persist($testSession);
        $expectedEncodedTestSession = $deliveryExecution->getQtiSdkEncodedTestSession();

        $this->sut->persistTestSession(
            $this->sut->fetchTestSession($deliveryExecution),
        );
        static::assertSame($expectedEncodedTestSession, $deliveryExecution->getQtiSdkEncodedTestSession());
    }

    public function titleDataProvider(): array
    {
        return [
            'QTI-based title' => [
                'expected' => self::QTI_TEST_TITLE,
                'deliveryExecution' => $this->createDeliveryExecution(),
            ],
            'Custom claim-based title' => [
                'expected' => 'Custom title',
                'deliveryExecution' => $this->createDeliveryExecution([
                    'custom' => [
                        LtiCustomSettings::PARAM_TITLES => json_encode([
                            ['type' => 'test', 'label' => 'Custom title'],
                        ]),
                    ],
                ]),
            ],
            'QTI-based title with not-matching custom claims' => [
                'expected' => self::QTI_TEST_TITLE,
                'deliveryExecution' => $this->createDeliveryExecution([
                    'custom' => [
                        LtiCustomSettings::PARAM_TITLES => json_encode([
                            ['type' => 'item', 'label' => 'Custom title'],
                        ]),
                    ],
                ]),
            ],
            'QTI-based title with an empty test-level custom claim' => [
                'expected' => self::QTI_TEST_TITLE,
                'deliveryExecution' => $this->createDeliveryExecution([
                    'custom' => [
                        LtiCustomSettings::PARAM_TITLES => json_encode([
                            ['type' => 'test', 'label' => ''],
                        ]),
                    ],
                ]),
            ],
            'QTI-based title with empty custom claims' => [
                'expected' => self::QTI_TEST_TITLE,
                'deliveryExecution' => $this->createDeliveryExecution([
                    'custom' => [
                        LtiCustomSettings::PARAM_TITLES => json_encode([]),
                    ],
                ]),
            ],
        ];
    }

    private function createDeliveryExecution(array $ltiLaunchParameters = [], string $deliveryExecutionId = 'userId#Basic#resultId#tenantId'): DeliveryExecution
    {
        return $this->createTestDeliveryExecution(
            $deliveryExecutionId,
            'Basic',
            'tenantId',
            $ltiLaunchParameters,
            null,
        );
    }
}
