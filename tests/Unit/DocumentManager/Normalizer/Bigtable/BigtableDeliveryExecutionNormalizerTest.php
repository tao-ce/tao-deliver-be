<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer\Bigtable;

use App\DocumentManager\Normalizer\Bigtable\BigtableDeliveryExecutionNormalizer;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\ExtraStateData\OverallComment;
use App\Helper\Date;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\ExternalTimerDefinitionTestingTrait;
use Carbon\Carbon;
use Exception;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\TestCase;

class BigtableDeliveryExecutionNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use ExternalTimerDefinitionTestingTrait;

    /** @var BigtableDeliveryExecutionNormalizer */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2020-01-01'));

        $this->subject = new BigtableDeliveryExecutionNormalizer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(BigtableDocumentDriver::class);

        $this->assertTrue($this->subject->supports($driverMock, DeliveryExecution::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
        $this->assertFalse($this->subject->supports($this->createMock(DocumentDriverInterface::class), DeliveryExecution::class));
    }

    public function testDenormalizationSuccess(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            extraStateData: DeliveryExecutionExtraStateData::fromArray(['attempt' => 1]),
        );
        $deliveryExecution->clearUpdates();

        $driverData = $this->getTestDocumentDriverData($deliveryExecution);

        $this->assertEquals($deliveryExecution, $this->subject->denormalizeDocument($driverData, DeliveryExecution::class));
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize delivery execution with id: "userId#deliveryId#resultId#tenantId" with errorMessage: Undefined array key "data"');

        $this->subject->denormalizeDocument($this->createTestDocumentDriverData('userId#deliveryId#resultId#tenantId'), DeliveryExecution::class);
    }

    /**
     * @dataProvider deliveryExecutionParameterDataProvider
     */
    public function testNormalizationSuccess(?string $lisResultSourcedId, ?string $qtiSdkEncodedTestSession): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['ltiLaunchParams', 'result_id' => $lisResultSourcedId],
            $qtiSdkEncodedTestSession,
            DeliveryExecutionExtraStateData::fromArray(['attempt' => 1]),
            locale: 'en-US',
        );
        $deliveryExecution->clearUpdates();

        $documentDriverData = $this->subject->normalizeDocument($deliveryExecution);
        $data = $documentDriverData->getData()['data'];

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($deliveryExecution->getId(), $documentDriverData->getId());
        $this->assertEquals($deliveryExecution->getStartedAt()->format(Date::DEFAULT_FORMAT), $data['startedAt']);
        $this->assertEquals($deliveryExecution->getLtiLaunchParameters(), unserialize(gzuncompress($data['ltiLaunchParameters'])));
        $this->assertEquals($deliveryExecution->getQtiSdkEncodedTestSession(), gzuncompress($data['qtiSdkEncodedTestSession']));
        $this->assertEquals($deliveryExecution->getExtraStateData(), $this->getDenormalizedExtraStateData(unserialize(gzuncompress($data['extraStateData']))));
        $this->assertEquals($deliveryExecution->getStatus(), $data['status']);
        $this->assertEquals($deliveryExecution->isDeleted(), $data['isDeleted']);
        $this->assertEquals($deliveryExecution->getLocale(), $data['locale']);
        $this->assertEquals(1, $data['attempt']);
        $this->assertEquals(
            $deliveryExecution,
            $this->subject->denormalizeDocument(new DocumentDriverData($documentDriverData->getId(), [
                'data' => array_map(
                    fn($value) => [
                        ['value' => $value],
                    ],
                    $data,
                ),
            ]), DeliveryExecution::class),
        );
    }

    public function testNormalizationExcludesExtraStateColumn(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['ltiLaunchParams'],
            '',
            DeliveryExecutionExtraStateData::fromArray(['attempt' => 1]),
            locale: 'en-US',
        );
        $deliveryExecution->clearUpdates();

        $deliveryExecution
            ->setItemAttachments('item-1', [])
            ->addTemporaryItemState('item-1', '')
            ->setRequestIp('127.0.0.1');
        $documentDriverData = $this->subject->normalizeDocument($deliveryExecution);
        $this->assertEquals(
            ['attachments', 'temporaryItemStates', 'requestIp', 'updatedAt'],
            $documentDriverData->getUpdatedData(),
        );
    }

    public function testNormalizationKeepsExtraStateColumn(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['ltiLaunchParams'],
            '',
            DeliveryExecutionExtraStateData::fromArray(['attempt' => 1]),
            locale: 'en-US',
        );
        $deliveryExecution->clearUpdates();

        $deliveryExecution->withItemOverallComment('item-1', new OverallComment(0, ''));
        $documentDriverData = $this->subject->normalizeDocument($deliveryExecution);
        $this->assertEquals(
            ['itemsOverallComments', 'extraStateData', 'updatedAt'],
            $documentDriverData->getUpdatedData(),
        );
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

    public function deliveryExecutionParameterDataProvider(): array
    {
        return [
            [
                'lisResultSourcedid' => 'lisResultSourcedid',
                'qtiSdkEncodedTestSession' => 'lisResultSourcedid',
            ],
            [
                'lisResultSourcedid' => null,
                'qtiSdkEncodedTestSession' => null,
            ],
        ];
    }

    private function getTestDocumentDriverData(DeliveryExecution $deliveryExecution): DocumentDriverDataInterface
    {
        return $this->createTestDocumentDriverData('userId#deliveryId#resultId#tenantId', [
            'data' => [
                'deliveryId' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getDeliveryId(),
                        'timestamp' => '12315321',
                    ],
                ],
                'tenantId' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getTenantId(),
                        'timestamp' => '12315321',
                    ],
                ],
                'startedAt' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getStartedAt()->format(Date::DEFAULT_FORMAT),
                        'timestamp' => '12315321',
                    ],
                ],
                'ltiLaunchParameters' => [
                    [
                        'label' => '',
                        'value' => gzcompress(serialize($deliveryExecution->getLtiLaunchParameters())),
                        'timestamp' => '12315321',
                    ],
                ],
                'resultId' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getResultId(),
                        'timestamp' => '12315321',
                    ],
                ],
                'qtiSdkEncodedTestSession' => [
                    [
                        'label' => '',
                        'value' => gzcompress($deliveryExecution->getQtiSdkEncodedTestSession()),
                        'timestamp' => '12315321',
                    ],
                ],
                'extraStateData' => [
                    [
                        'label' => '',
                        'value' => gzcompress(serialize($this->getNormalizedExtraStateData($deliveryExecution->getExtraStateData()))),
                        'timestamp' => '12315321',
                    ],
                ],
                'status' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getStatus(),
                        'timestamp' => '12315321',
                    ],
                ],
                'finishedAt' => [
                    [
                        'label' => '',
                        'value' => '',
                        'timestamp' => '12315321',
                    ],
                ],
                'closeAt' => [
                    [
                        'label' => '',
                        'value' => '',
                        'timestamp' => '12315321',
                    ],
                ],
                'updatedAt' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getUpdatedAt()?->format(Date::DEFAULT_FORMAT),
                        'timestamp' => '12315321',
                    ],
                ],
                'isDeleted' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->isDeleted(),
                        'timestamp' => '12315321',
                    ],
                ],
                'locale' => [
                    [
                        'label' => '',
                        'value' => $deliveryExecution->getLocale(),
                        'timestamp' => '12315321',
                    ],
                ],
            ],
        ]);
    }

    private function getNormalizedExtraStateData(DeliveryExecutionExtraStateData $deliveryExecutionExtraStateData): array
    {
        return $deliveryExecutionExtraStateData->toArray();
    }

    private function getNormalizedExternalTimerData(): string
    {
        return (string)$this->createExternalDefinitionTimerFromArray($this->timerDataExample);
    }

    private function getDenormalizedExtraStateData(array $data): DeliveryExecutionExtraStateData
    {
        return DeliveryExecutionExtraStateData::fromArray($data);
    }
}
