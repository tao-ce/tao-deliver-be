<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Generator\UuidGenerator;
use App\Qti\Extractor\QtiResultExtractor;
use App\Qti\Service\AssessmentResultService;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use League\Flysystem\FilesystemWriter;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExtractDeliveryExecutionResultServiceTest extends KernelTestCase
{
    use DomainTestingTrait;

    private AssessmentResultService|MockObject $assessmentResultServiceMock;
    private ExtractDeliveryExecutionResultService $subject;

    public function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::createFromTimestamp(1546297200));

        static::bootKernel();

        $generator = $this->createMock(UuidGenerator::class);
        $generator->method('generate')->willReturnOnConsecutiveCalls(
            'testResultId',
            'itemResultId1',
            'item1ResponseVariableId1',
            'item1ResponseVariableId2',
            'item1OutcomeVariableId1',
            'item1OutcomeVariableId2',
            'item1OutcomeVariableId3',
            'item1ResponseVariableId3',
            'item1TouchedOutcomeVariableId',
            'itemResultId2',
            'item2ResponseVariableId1',
            'item2ResponseVariableId2',
            'item2OutcomeVariableId1',
            'item2OutcomeVariableId2',
            'item2OutcomeVariableId3',
            'item2ResponseVariableId3',
            'item2TouchedOutcomeVariableId',
            'itemResultId3',
            'item3ResponseVariableId1',
            'item3ResponseVariableId2',
            'item3OutcomeVariableId1',
            'item3OutcomeVariableId2',
            'item3OutcomeVariableId3',
            'item3ResponseVariableId3',
            'item3TouchedOutcomeVariableId',
        );

        $this->assessmentResultServiceMock = $this->createMock(AssessmentResultService::class);
        $this->subject = new ExtractDeliveryExecutionResultService(
            static::getContainer()->get(QtiResultExtractor::class),
            $this->assessmentResultServiceMock,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testItWillExtractAndStore(): void
    {
        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INTERACTING,
        );

        $this->assessmentResultServiceMock
            ->expects($this->exactly(2))
            ->method('persist')
            ->withConsecutive(
                ["{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}", $this->isType('string')],
                [$deliveryExecution->getId(), $this->isType('string')],
            );

        $extractedData = $this->subject->extract($deliveryExecution);

        $this->assertSame($deliveryExecution->getId(), $extractedData['deliveryExecutionId']);
        $this->assertSame(0.3333333333333333, $extractedData['score']);
        $this->assertSame(3.0, $extractedData['maxScore']);
    }

    private function getDeliveryExecutionWithStartedTestSession(string $deliveryExecutionStatus, bool $sessionStarted = true): DeliveryExecution
    {
        $this->copyCompiledTestToStorage('deliveryId/compact-test.xml');

        $deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: ['ltiLaunchParams', 'user_id' => 'testTakerId'],
            testSession: '',
            extraStateData: new DeliveryExecutionExtraStateData(),
            status: $deliveryExecutionStatus,
            startedAt: Carbon::now(),
            finishedAt: Carbon::now(),
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

    private function copyCompiledTestToStorage(string $compactTestPath): void
    {
        /** @var FilesystemWriter $qtiCompiledDeliveriesStorage */
        $qtiCompiledDeliveriesStorage = static::getContainer()->get('qti_compiled_deliveries.storage');

        $qtiCompiledDeliveriesStorage->write(
            $compactTestPath,
            file_get_contents(__DIR__ . '/../../../Resources/Qti/CompiledPackages/Basic/compact-test.xml'),
        );
    }

    private function getISO8601RegExp(): string
    {
        return '/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})[+-](\d{2}):(\d{2})/';
    }
}
