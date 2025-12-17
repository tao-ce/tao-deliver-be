<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\DeliveryExecutionNormalizer;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;
use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use App\Helper\Date;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    /** @var DeliveryExecutionNormalizer */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2020-01-01'));

        $this->subject = new DeliveryExecutionNormalizer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(DocumentDriverInterface::class);

        $this->assertTrue($this->subject->supports($driverMock, DeliveryExecution::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
    }

    public function testDenormalizationSuccess(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $deliveryExecution->clearUpdates();

        $driverData = $this->createTestDocumentDriverData('userId#deliveryId#resultId#tenantId', [
            'startedAt' => $deliveryExecution->getStartedAt()->format(Date::DEFAULT_FORMAT),
            'extraStateData' => [
                'initialStartTimestamp' => $deliveryExecution->getStartedAt()->getTimestamp(),
                'flaggedItems' => $deliveryExecution->getExtraStateData()->getFlaggedItems(),
                'comments' => $deliveryExecution->getExtraStateData()->getComments(),
                'traceData' => $deliveryExecution->getExtraStateData()->getTraceData(),
                'toolStates' => $deliveryExecution->getExtraStateData()->getTraceData(),
                'itemStates' => $deliveryExecution->getExtraStateData()->getTraceData(),
                'uiEvents' => json_encode($deliveryExecution->getExtraStateData()->getUiEvents()),
                'assessmentEvents' => $deliveryExecution->getExtraStateData()->getAssessmentEvents(),
                'plagiarismReports' => array_map(static function (PlagiarismReport $report) {
                    return [
                        'id' => $report->getId(),
                        'createdAt' => $report->getCreatedAt(),
                        'itemId' => $report->getItemId(),
                        'responseId' => $report->getResponseId(),
                        'status' => $report->getStatus(),
                        'href' => $report->getHref(),
                    ];
                }, $deliveryExecution->getExtraStateData()->getPlagiarismReports()),
                'durationStorage' => [
                    'serverDurations' => array_map(static function (ServerDuration $serverDuration) {
                        return [
                            'qtiComponentIdentifier' => $serverDuration->getQtiComponentIdentifier(),
                            'duration' => $serverDuration->getDuration(),
                        ];
                    }, $deliveryExecution->getExtraStateData()->getDurationStorage()->getServerDurations()),
                ],
            ],
            'ltiLaunchParameters' => $deliveryExecution->getLtiLaunchParameters(),
            'qtiSdkEncodedTestSession' => $deliveryExecution->getQtiSdkEncodedTestSession(),
            'status' => $deliveryExecution->getStatus(),
            'finishedAt' => null,
            'closeAt' => null,
            'updatedAt' => Carbon::now()->format(Date::DEFAULT_FORMAT),
            'isDeleted' => $deliveryExecution->isDeleted(),
        ]);

        $this->assertEquals($deliveryExecution, $this->subject->denormalizeDocument($driverData, DeliveryExecution::class));
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize delivery execution');

        $this->subject->denormalizeDocument($this->createTestDocumentDriverData('userId#deliveryId#resultId#tenantId'), DeliveryExecution::class);
    }

    public function testNormalizationSuccess(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $documentDriverData = $this->subject->normalizeDocument($deliveryExecution);
        $data = $documentDriverData->getData();

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($deliveryExecution->getId(), $documentDriverData->getId());
        $this->assertEquals($deliveryExecution->getStartedAt()->format(Date::DEFAULT_FORMAT), $data['startedAt']);
        $this->assertEquals($deliveryExecution->getLtiLaunchParameters(), $data['ltiLaunchParameters']);
        $this->assertEquals($deliveryExecution->getQtiSdkEncodedTestSession(), $data['qtiSdkEncodedTestSession']);
        $this->assertEquals($deliveryExecution->getStatus(), $data['status']);
        $this->assertSame($deliveryExecution->isDeleted(), $data['isDeleted']);

        $extraStateData = $deliveryExecution->getExtraStateData();
        $this->assertEquals($extraStateData->getFlaggedItems(), $data['extraStateData']['flaggedItems']);
        $this->assertEquals($extraStateData->getComments(), $data['extraStateData']['comments']);
        $this->assertEquals($extraStateData->getTraceData(), $data['extraStateData']['traceData']);
        $this->assertEquals($extraStateData->getToolStates(), $data['extraStateData']['toolStates']);
        $this->assertEquals($extraStateData->getItemStates(), $data['extraStateData']['itemStates']);
        $this->assertEquals($extraStateData->getUiEvents(), json_decode($data['extraStateData']['uiEvents'], true));
        $this->assertEquals($extraStateData->getAssessmentEvents(), $data['extraStateData']['assessmentEvents']);
        $durationStorage = $extraStateData->getDurationStorage();
        $this->assertEquals($durationStorage->getServerDurations(), $data['extraStateData']['durationStorage']['serverDurations']);
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot normalize delivery execution');

        $document = $this->getMockBuilder(DeliveryExecution::class)
            ->setConstructorArgs([
                'userId#deliveryId#resultId#tenantId',
                'deliveryId',
                'tenantId',
                Carbon::now(),
                ['result_id' => 'resultId'],
                null,
            ])->onlyMethods(['getUpdates'])
            ->getMock();
        $document->method('getUpdates')->willThrowException(new Exception());
        $this->subject->normalizeDocument($document);
    }
}
