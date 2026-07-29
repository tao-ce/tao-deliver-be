<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Cache\CacheTrait;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Generator\Asset\CloudStorageSignedUrlGenerator;
use App\Generator\UrlGenerator;
use App\ImageResponse\Service\ImageResponseReaderService;
use App\Lti\LtiCustomSettings;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Service\ApplicationInfoService;
use App\TestItemAttachment\Service\AttachmentRegistry;
use App\Service\DeliveryExecution\DeliveryExecutionCommentService;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Lti\LtiTokenResolverInterface;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use App\TestRunner\ItemEnricher\Contract\ItemEnricherInterface;
use App\TestRunner\Service\GetItemDataService;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\TestSessionInitiator;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use Monolog\Logger;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiDuration;
use qtism\common\datatypes\QtiString;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\common\VariableCollection;
use qtism\runtime\pci\json\Marshaller;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionCollection;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Environment;

class GetItemServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use FilesystemTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;
    use CacheTrait;

    public const ITEM_ID = 'Item-Q02';

    private SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry;
    private DeliveryExecution $deliveryExecution;
    private TestSessionAccessorFactory $testSessionAccessorFactory;
    private Router $urlGenerator;
    private AttachmentRegistry $attachmentRegistry;

    public function setUp(): void
    {
        Carbon::setTestNow(Carbon::create(2000, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        static::bootKernel();
        static::getContainer()->get('serializer');

        $this->setUpTestLogHandler();

        $applicationInfoService = $this->createMock(ApplicationInfoService::class);
        $applicationInfoService
            ->method('getBackendUrl')
            ->willReturn(getenv('DELIVER_BACKEND_URL'));

        $this->signedUrlGeneratorRegistry = static::getContainer()->get(SignedUrlGeneratorRegistry::class);
        $this->testSessionAccessorFactory = $this->createMock(TestSessionAccessorFactory::class);
        $this->urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);
        $this->attachmentRegistry = $this->createMock(AttachmentRegistry::class);

        $this->copyCompiledTestToStorage(
            ['compact-test.xml', 'Item-Q01/item.json'],
            'BasicAssets',
        );
        $this->copyCompiledTestToStorage(
            ['compact-test.xml', 'Item-Q02/item.json', 'Item-Q02/portableElements.json'],
            'BasicAssets',
        );
        $this->copyCompiledTestToStorage(
            ['Item-Q04/item.json', 'Item-Q04/portableElements.json'],
            'BasicAssets',
        );

        $this->deliveryExecution = $this->getDeliveryExecution();
    }

    public function tearDown(): void
    {
        static::getContainer()->get('request_stack')->pop();
        Carbon::setTestNow();
    }

    public function testItThrowsExceptionWhenNoPciCompiledFile(): void
    {
        $subject = $this->getSubject();

        $this->expectException(UnableToReadFile::class);
        $this->expectExceptionMessage(
            'Unable to read file from location: /BasicAssets/Item-Q01/portableElements.json.',
        );

        $subject->getItem(
            $this->deliveryExecution,
            'Item-Q01',
        );
    }

    public function testItCanReturnItem(): void
    {
        $subject = $this->getSubject();

        $response = $subject->getItem(
            $this->deliveryExecution,
            self::ITEM_ID,
        );

        $this->assertDummyItemResponse($response);

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - got item data %s from the compiled delivery storage',
                self::ITEM_ID,
            ),
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - put item data %s in the cache',
                self::ITEM_ID,
            ),
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[userId#BasicAssets#resultId#tenantId][GetItemService] - got item state of item %s',
                self::ITEM_ID,
            ),
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[userId#BasicAssets#resultId#tenantId][GetItemService] - start creating response of item %s',
                self::ITEM_ID,
            ),
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[userId#BasicAssets#resultId#tenantId][GetItemService] - finish creating response of item %s',
                self::ITEM_ID,
            ),
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public function testItCanReturnItemWithPci(): void
    {
        $subject = $this->getSubject();

        $response = $subject->getItem(
            $this->deliveryExecution,
            'Item-Q04',
        );

        self::assertEquals('', $response['baseUrl']);
        self::assertEquals('Item-Q04', $response['itemIdentifier']);
        self::assertArrayHasKey('itemData', $response);
        self::assertArrayHasKey('data', $response['itemData']);
        self::assertArrayHasKey('assets', $response['itemData']);
        self::assertArrayHasKey('css', $response['itemData']['assets']);
        self::assertArrayHasKey('tao-user-styles.css', $response['itemData']['assets']['css']);
        self::assertArrayHasKey('type', $response['itemData']);

        $baseSignedUrl = '//tao_deliver_be_nginx';

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][GetItemService] - got portable elements of item Item-Q04',
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][ModifyAssetsLinksEnricher] - modified assets links of item Item-Q04',
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        foreach ($response['portableElements']['pci'] as $interaction => $content) {
            self::assertArrayHasKey('0', $content);
            foreach ($content as $pci) {
                foreach ($pci['runtime'] as $type => $data) {
                    if (is_array($data)) {
                        foreach ($data as $url) {
                            self::assertStringContainsString($baseSignedUrl, $url);
                        }
                    } else {
                        self::assertStringContainsString($baseSignedUrl, $data);
                    }
                }
            }
        }
    }

    public function testItWillFetchItemDataFromCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
            )
            ->willReturnOnConsecutiveCalls(
                ['assets' => [['assets']]],
                ['pci' => [], 'pic' => []],
            );

        $subject = $this->getSubject($cache);

        $subject->getItem($this->deliveryExecution, self::ITEM_ID);

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - got item data %s from the cache',
                self::ITEM_ID,
            ),
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public function testDynamicItemDataNotRequestItemFromCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->never())
            ->method('get')
            ->with(
                $this->getCacheKey(
                    'BasicAssets',
                    self::ITEM_ID,
                    QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                ),
            );

        $subject = $this->getSubject($cache);

        $subject->getItemDynamicData($this->deliveryExecution, self::ITEM_ID);
    }

    public function testItemDynamicDataIsPure(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $subject = $this->getSubject($cache);
        $response = $subject->getItemDynamicData($this->deliveryExecution, self::ITEM_ID);

        $this->assertArrayNotHasKey('portableElements', $response);
        $this->assertArrayNotHasKey('itemData', $response);
        $this->assertArrayHasKey('itemState', $response);
    }

    public function testItemStaticDataIsPure(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $subject = $this->getSubject($cache);
        $response = $subject->getItemStaticData($this->deliveryExecution, self::ITEM_ID);

        $this->assertArrayNotHasKey('itemState', $response);
        $this->assertArrayHasKey('portableElements', $response);
        $this->assertArrayHasKey('itemData', $response);
    }

    public function testDynamicItemDataProvideOnReviewMode(): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            '{"state": "dummyItemState"}',
            [
                'is_anonymous' => true,
            ],
        );

        $subject = $this->getSubject($this->createMock(CacheInterface::class));

        $response = $subject->getItemDynamicData($deliveryExecution, self::ITEM_ID);
        $this->assertSame(
            '{"state":"dummyItemState"}',
            $response['itemState'],
        );
    }

    public function testFeedbacksDataIsRemovedFromResponse(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive(
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
            )
            ->willReturnOnConsecutiveCalls(
                ['data' => ['feedbacks' => [[]]]],
            );

        $subject = $this->getSubject($cache);

        $itemData = $subject->getItem($this->deliveryExecution, self::ITEM_ID);

        $this->assertArrayNotHasKey('feedbacks', $itemData['itemData']['data']);
    }

    public function testItWorksProperlyEvenIfGetInCacheIsNotWorkingAndLogError(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
            )
            ->willThrowException(new CacheException('cache storage unavailable'));

        $subject = $this->getSubject($cache);

        $subject->getItem($this->deliveryExecution, self::ITEM_ID);

        $this->assertHasLogRecord(['message' => 'cache storage unavailable',], Logger::ERROR);
    }

    public function testItWorksProperlyEvenIfSetInCacheIsNotWorkingAndLogError(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $executionNumber = 0;
        $cache
            ->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        self::ITEM_ID,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
            )
            ->willReturnCallback(static function () use (&$executionNumber) {
                $executionNumber++;
                if ($executionNumber === 1) {
                    return null;
                }

                if ($executionNumber === 2) {
                    throw new CacheException('cache storage unavailable');
                }
            });

        $subject = $this->getSubject($cache);

        $subject->getItem($this->deliveryExecution, self::ITEM_ID);

        $this->assertHasLogRecord(['message' => 'cache storage unavailable',], Logger::ERROR);
    }

    public function testItWillThrownAnExceptionIfErrorComingFromStorage(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(1))
            ->method('get')
            ->with(
                $this->getCacheKey(
                    'BasicAssets',
                    self::ITEM_ID,
                    QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                ),
            )
            ->willReturn(null);
        $storage = $this->createMock(FilesystemReader::class);
        $storage
            ->method('read')
            ->willThrowException(new UnableToReadFile('File not found'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('File not found');

        $subject = $this->getSubject($cache, $storage);

        $subject->getItem($this->deliveryExecution, self::ITEM_ID);
    }

    public function testItWillThrownAnExceptionIfErrorComingFromStorageAndLogError(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(1))
            ->method('get')
            ->with(
                $this->getCacheKey(
                    'BasicAssets',
                    self::ITEM_ID,
                    QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                ),
            )
            ->willReturn(null);
        $storage = $this->createMock(FilesystemReader::class);
        $exception = new UnableToReadFile('File not found');
        $storage
            ->method('read')
            ->willThrowException($exception);

        $subject = $this->getSubject($cache, $storage);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage($exception->getMessage());
        $subject->getItem($this->deliveryExecution, self::ITEM_ID);
    }

    public function testItAddsSignedUrlForFileHashResponse(): void
    {
        $deliveryExecution = $this->getDeliveryExecution(
            '{"RESPONSE":{"response":{"base":{"fileHash":{"id":"path/to/filename.ext","data":"HASH","mime":"image/png","name":"filename.ext"}}},"validity":true}}',
        );

        $subject = $this->getSubject();

        $response = $subject->getItem(
            $deliveryExecution,
            self::ITEM_ID,
        );

        $path = 'path/to/filename.ext';
        $itemState = json_decode($response['itemState'], true);
        $this->assertEquals(
            $this->signedUrlGeneratorRegistry
                ->getGenerator(CloudStorageSignedUrlGenerator::NAME)
                ->generateDownloadUrl(
                    $path,
                    $this->urlGenerator->generate(
                        'api_v1_attachments_download_file',
                        compact('path'),
                        UrlGeneratorInterface::NETWORK_PATH,
                    ),
                ),
            $itemState['RESPONSE']['response']['base']['fileHash']['link'],
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][ModifyAssetsLinksEnricher] - modified assets links of item ' . self::ITEM_ID,
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public function testItHandlesEmptyItemStateProperly(): void
    {
        $deliveryExecution = $this->getDeliveryExecution('{}');

        $subject = $this->getSubject();
        $response = $subject->getItem($deliveryExecution, self::ITEM_ID);

        $this->assertNull($response['itemState']);
    }

    public function testItHandlesReviewModeWithoutAnswers(): void
    {
        $subject = $this->getSubject();

        $deliveryExecution = $this->launchDeliveryExecutionReview(
            'dummyItemState',
            [
                'is_anonymous' => true,
            ],
        );

        $response = $subject->getItem(
            $deliveryExecution,
            self::ITEM_ID,
        );

        $this->assertDummyItemResponse($response);
    }

    /**
     * @dataProvider providerTestItHandlesReviewModeWithTestTakerAnswer
     */
    public function testItHandlesReviewModeWithTestTakerAnswer(string $itemState, string $expected): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview($itemState);

        $subject = $this->getSubject();
        $response = $subject->getItem($deliveryExecution, self::ITEM_ID);

        $this->assertArrayNotHasKey('correctResponse', $response);
        $this->assertJsonStringEqualsJsonString($expected, $response['itemResponse']);

        $this->assertHasLogRecordWithMessage(
            "[{$deliveryExecution->getId()}][GetItemService] - added item response for review mode to item " . self::ITEM_ID,
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public static function providerTestItHandlesReviewModeWithTestTakerAnswer(): array
    {
        return [
            'Review a standard interaction' => [
                '{"RESPONSE":{"response":{"base":{"string":"value2"}}}}',
                '{"RESPONSE":{"base":{"string":"value2"}}}',
            ],
            'Review a file upload interaction' => [
                <<<'JSON'
                {
                  "RESPONSE": {
                    "response": {
                      "base": {
                        "fileHash": {
                          "localFile": [],
                          "name": "Screen Shot 2021-01-20 at 09.13.03.png",
                          "mime": "image/png",
                          "data": "4cb01d155fc09c198c579f87470ef587167ef39813097cbd10ae905f6b0bd789",
                          "id": "0a92fab3230134cca6eadd9898325b9b2ae67998-687479d164dc18426836d458a28b309cf1ca5328-044e9ac4-a107-49dc-a326-07370c96f298-5/item-1/RESPONSE/1/Screen Shot 2021-01-20 at 09.13.03.png"
                        }
                      }
                    }
                  }
                }
                JSON,
                <<<'JSON'
                {
                  "RESPONSE": {
                    "base": {
                      "fileHash": {
                        "localFile": [],
                        "name": "Screen Shot 2021-01-20 at 09.13.03.png",
                        "mime": "image/png",
                        "data": "4cb01d155fc09c198c579f87470ef587167ef39813097cbd10ae905f6b0bd789",
                        "id": "0a92fab3230134cca6eadd9898325b9b2ae67998-687479d164dc18426836d458a28b309cf1ca5328-044e9ac4-a107-49dc-a326-07370c96f298-5/item-1/RESPONSE/1/Screen Shot 2021-01-20 at 09.13.03.png",
                        "link": "//tao_deliver_be_nginx/api/v1/attachments/0a92fab3230134cca6eadd9898325b9b2ae67998-687479d164dc18426836d458a28b309cf1ca5328-044e9ac4-a107-49dc-a326-07370c96f298-5/item-1/RESPONSE/1/Screen%20Shot%202021-01-20%20at%2009.13.03.png?path=0a92fab3230134cca6eadd9898325b9b2ae67998-687479d164dc18426836d458a28b309cf1ca5328-044e9ac4-a107-49dc-a326-07370c96f298-5%2Fitem-1%2FRESPONSE%2F1%2FScreen+Shot+2021-01-20+at+09.13.03.png",
                        "downloadUrl": "//tao_deliver_be_nginx/api/v1/attachments/0a92fab3230134cca6eadd9898325b9b2ae67998-687479d164dc18426836d458a28b309cf1ca5328-044e9ac4-a107-49dc-a326-07370c96f298-5/item-1/RESPONSE/1/Screen%20Shot%202021-01-20%20at%2009.13.03.png?path=0a92fab3230134cca6eadd9898325b9b2ae67998-687479d164dc18426836d458a28b309cf1ca5328-044e9ac4-a107-49dc-a326-07370c96f298-5%2Fitem-1%2FRESPONSE%2F1%2FScreen+Shot+2021-01-20+at+09.13.03.png"
                      }
                    }
                  }
                }
                JSON,
            ],
        ];
    }

    public function testItHandlesReviewModeWithTestTakerAndCorrectAnswers(): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            '{"RESPONSE":{"response":{"base":{"string":"value2"}}}}',
            [
                'custom' => [
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT => true,
                ],
            ],
        );

        $variable1 = new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::STRING);
        $variable1->setCorrectResponse(new QtiString('value1'));

        $variable2 = new ResponseVariable('duration', Cardinality::SINGLE, BaseType::DURATION);
        $variable2->setCorrectResponse(new QtiDuration('P3Y6M4DT12H30M5S'));

        $variables = new VariableCollection([
            $variable1,
            $variable2,
        ]);

        $assessmentItemSession = $this->createMock(AssessmentItemSession::class);
        $assessmentItemSession
            ->method('getAllVariables')
            ->willReturn($variables);

        $assessmentItemSessionCollection = $this->createMock(AssessmentItemSessionCollection::class);
        $assessmentItemSessionCollection
            ->method('current')
            ->willReturn($assessmentItemSession);

        $assessmentTestSession = $this->createMock(AssessmentTestSession::class);
        $assessmentTestSession
            ->method('getSessionId')
            ->willReturn($deliveryExecution->getId());
        $assessmentTestSession
            ->method('getAssessmentItemSessions')
            ->with(self::ITEM_ID)
            ->willReturn($assessmentItemSessionCollection);

        $assessmentTestSession
            ->method('getCurrentAssessmentItemSession')
            ->willReturn($this->createMock(AssessmentItemSession::class));

        $testSessionAccessor = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessor
            ->method('retrieve')
            ->willReturn($assessmentTestSession);

        $this->testSessionAccessorFactory
            ->method('create')
            ->with($deliveryExecution)
            ->willReturn($testSessionAccessor);

        $subject = $this->getSubject();
        $response = $subject->getItem($deliveryExecution, self::ITEM_ID);

        $this->assertSame('{"RESPONSE":{"base":{"string":"value1"}}}', $response['correctResponse']);
        $this->assertSame('{"RESPONSE":{"base":{"string":"value2"}}}', $response['itemResponse']);

        $this->assertHasLogRecordWithMessage(
            "[{$deliveryExecution->getId()}][GetItemService] - added correct answers for review mode to item " . self::ITEM_ID,
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public function testItHandlesReviewModeWithPlagiarismReports(): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            '{"RESPONSE":{"response":{"base":{"string":"value2"}}}}',
            [
                'custom' => [
                    LtiCustomSettings::PARAM_REVIEW_EXTRA_INFO => json_encode(['provider']),
                ],
            ],
        );

        $deliveryExecution->addPlagiarismReport(
            new PlagiarismReport(
                'provider',
                'uuid',
                Carbon::parse('2022-02-18T10:42:34+01:00'),
                self::ITEM_ID,
                'response-id',
                'suspicious',
            ),
        );

        $subject = $this->getSubject();

        $response = $subject->getItem($deliveryExecution, self::ITEM_ID);

        $expected = [
            'plagiarismReports' => [
                [
                    'provider' => 'provider',
                    'responses' => [
                        'response-id' => [
                            'id' => 'uuid',
                            'status' => 'suspicious',
                            'href' => '',
                            'reportUrl' => '//tao_deliver_be_nginx/api/v1/delivery-executions/review%23userId%23BasicAssets%23resultId%23tenantId/hbl/uuid',
                        ],
                    ],
                ],
            ],
            'scoring' => ['comments' => ['inline' => [], 'annotations' => []]],
        ];

        $this->assertSame($expected, $response['extraData']);
    }


    public function testReviewInlineCommentIsResponded(): void
    {
        $this->mockRequestTokenValue($this->tokenStub());
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            '{"RESPONSE":{"response":{"base":{"string":"value2"}}}}',
            [
                'custom' => [
                    LtiCustomSettings::PARAM_REVIEW_EXTRA_INFO => json_encode(['provider']),
                ],
            ],
        );
        $deliveryExecution->addReviewInlineComment(
            'scorer-1',
            self::ITEM_ID,
            ['comment' => 'text'],
        );
        $subject = $this->getSubject();
        $response = $subject->getItem($deliveryExecution, self::ITEM_ID);

        $expected = [
            'scoring' => ['comments' => ['inline' => ['comment' => 'text'], 'annotations' => []]],
        ];

        $this->assertSame($expected, $response['extraData']);
    }

    public function testItHandlesAttachmentsReview(): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            attachments: [
                self::ITEM_ID => [
                    [
                        'id' => '55dce224-5ec8-4b20-a3d0-99f6d1935198',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 1,
                    ],
                ],
            ],
        );

        $this
            ->attachmentRegistry
            ->method('resolveAttachments')
            ->with($deliveryExecution->getTenantId(), ['55dce224-5ec8-4b20-a3d0-99f6d1935198'])
            ->willReturn([
                '55dce224-5ec8-4b20-a3d0-99f6d1935198' => [
                    'url' => 'https://content-service/55dce224-5ec8-4b20-a3d0-99f6d1935198',
                ],
            ]);
        $subject = $this->getSubject();
        $response = $subject->getItemDynamicData($deliveryExecution, self::ITEM_ID);

        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'RESPONSE' => [
                    'response' => [
                        'base' => [
                            'string' => $this->createAttachmentsItemState(
                                ['https://content-service/55dce224-5ec8-4b20-a3d0-99f6d1935198'],
                            ),
                        ],
                    ],
                ],
            ]),
            $response['itemState'],
        );
    }

    public function testItAppendsAttachmentsToItemStateInReviewMode(): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            itemState: '{"RESPONSE":{"response":{"base":{"string":"existing response"}}}}',
            attachments: [
                self::ITEM_ID => [
                    [
                        'id' => '55dce224-5ec8-4b20-a3d0-99f6d1935198',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 1,
                    ],
                ],
            ],
        );

        $this
            ->attachmentRegistry
            ->method('resolveAttachments')
            ->with($deliveryExecution->getTenantId(), ['55dce224-5ec8-4b20-a3d0-99f6d1935198'])
            ->willReturn([
                '55dce224-5ec8-4b20-a3d0-99f6d1935198' => [
                    'url' => 'https://content-service/55dce224-5ec8-4b20-a3d0-99f6d1935198',
                ],
            ]);
        $subject = $this->getSubject();
        $response = $subject->getItemDynamicData($deliveryExecution, self::ITEM_ID);

        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'RESPONSE' => [
                    'response' => [
                        'base' => [
                            'string' => 'existing response' . $this->createAttachmentsItemState(
                                ['https://content-service/55dce224-5ec8-4b20-a3d0-99f6d1935198'],
                            ),
                        ],
                    ],
                ],
            ]),
            $response['itemState'],
        );
    }

    public function testItSortsMultipleAttachmentsInReviewMode(): void
    {
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            attachments: [
                self::ITEM_ID => [
                    [
                        'id' => 'df030a1f-8448-408b-be6a-be62d3788c21',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 3,
                    ],
                    [
                        'id' => 'a66fa124-0d72-43f3-8d96-267c9bf0f708',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 8,
                    ],
                    [
                        'id' => '0b3e75a5-a9ca-4a0f-a251-6c08a91d180c',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 1,
                    ],
                    [
                        'id' => 'missing-from-content-service',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 7,
                    ],
                ],
                self::ITEM_ID . '_unused' => [
                    [
                        'id' => '84dc5657-8ad8-438b-9185-a815d704ec91',
                        'responseId' => 'RESPONSE',
                        'createdAt' => '2025-12-01T14:11:02Z',
                        'pageNumber' => 1,
                    ],
                ],
            ],
        );

        $this
            ->attachmentRegistry
            ->method('resolveAttachments')
            ->with(
                $deliveryExecution->getTenantId(),
                [
                    '0b3e75a5-a9ca-4a0f-a251-6c08a91d180c',
                    'df030a1f-8448-408b-be6a-be62d3788c21',
                    'missing-from-content-service',
                    'a66fa124-0d72-43f3-8d96-267c9bf0f708',
                ],
            )
            ->willReturn([
                'df030a1f-8448-408b-be6a-be62d3788c21' => [
                    'url' => 'https://content-service/df030a1f-8448-408b-be6a-be62d3788c21',
                ],
                'a66fa124-0d72-43f3-8d96-267c9bf0f708' => [
                    'url' => 'https://content-service/a66fa124-0d72-43f3-8d96-267c9bf0f708',
                ],
                '0b3e75a5-a9ca-4a0f-a251-6c08a91d180c' => [
                    'url' => 'https://content-service/0b3e75a5-a9ca-4a0f-a251-6c08a91d180c',
                ],
            ]);
        $subject = $this->getSubject();
        $response = $subject->getItemDynamicData($deliveryExecution, self::ITEM_ID);

        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'RESPONSE' => [
                    'response' => [
                        'base' => [
                            'string' => $this->createAttachmentsItemState([
                                'https://content-service/0b3e75a5-a9ca-4a0f-a251-6c08a91d180c',
                                'https://content-service/df030a1f-8448-408b-be6a-be62d3788c21',
                                'https://content-service/a66fa124-0d72-43f3-8d96-267c9bf0f708',
                            ]),
                        ],
                    ],
                ],
            ]),
            $response['itemState'],
        );
    }

    public function testReviewAnnotationsCommentIsResponded(): void
    {
        $this->mockRequestTokenValue($this->tokenStub());
        $deliveryExecution = $this->launchDeliveryExecutionReview(
            '{"RESPONSE":{"response":{"base":{"string":"value2"}}}}',
            [
                'custom' => [
                    LtiCustomSettings::PARAM_REVIEW_EXTRA_INFO => json_encode(['provider']),
                ],
            ],
        );
        $deliveryExecution->addAnnotationComment(
            'scorer-1',
            self::ITEM_ID,
            ['markingSymbols' => ['color' => 'red']],
        );
        $subject = $this->getSubject();
        $response = $subject->getItem($deliveryExecution, self::ITEM_ID);

        $expected = [
            'scoring' => [
                'comments' => [
                    'inline' => [],
                    'annotations' => [
                        'markingSymbols' => ['color' => 'red'],
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $response['extraData']);
    }
    private function createAttachmentsItemState(array $urls): string
    {
        return implode(
            '',
            array_map(
                static fn(
                    string $url,
                ) => "<figure class=\"image\" style=\"margin-left:auto;margin-right:auto;text-align:center\"><img src=\"$url\"></figure>\n",
                $urls,
            ),
        );
    }

    private function launchDeliveryExecutionReview(
        string $itemState = '{}',
        array $claims = [],
        array $attachments = [],
    ): DeliveryExecution {
        $claims['custom'][LtiCustomSettings::PARAM_REVIEW_MODE] = true;
        $deliveryExecution = $this->getDeliveryExecution($itemState, $claims);
        return $deliveryExecution->setAttachments($attachments);
    }

    private function assertDummyItemResponse(array $response): void
    {
        $this->assertEquals('', $response['baseUrl']);
        $this->assertEquals(self::ITEM_ID, $response['itemIdentifier']);
        $this->assertArrayHasKey('itemData', $response);
        $this->assertArrayHasKey('data', $response['itemData']);
        $this->assertArrayHasKey('assets', $response['itemData']);
        $this->assertArrayHasKey('img', $response['itemData']['assets']);
        $this->assertArrayHasKey('logo.png', $response['itemData']['assets']['img']);
        $this->assertArrayHasKey('type', $response['itemData']);

        $path = $this->buildPathFor('BasicAssets', self::ITEM_ID, 'logo.png');
        $this->assertEquals(
            $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl(
                $path,
            ),
            $response['itemData']['assets']['img']['logo.png'],
        );
    }

    private function getDeliveryExecution(
        string $itemState = 'dummyItemState',
        array $ltiParameters = ['ltiLaunchParameters'],
    ): DeliveryExecution {
        $prefix = filter_var(
            $ltiParameters['custom'][LtiCustomSettings::PARAM_REVIEW_MODE] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        )
            ? DeliveryExecution::REVIEW_MODE_PREFIX . DeliveryExecution::DOCUMENT_KEY_DELIMITER
            : '';

        $deliveryExecution = $this->createTestDeliveryExecution(
            "{$prefix}userId#BasicAssets#resultId#tenantId",
            'BasicAssets',
            'tenantId',
            $ltiParameters,
        );

        $deliveryExecution->addItemState(self::ITEM_ID, $itemState);

        return $deliveryExecution;
    }

    private function getSubject(?CacheInterface $cache = null, ?FilesystemReader $storage = null): GetItemService
    {
        $cache = $cache ?? static::getContainer()->get(CacheInterface::class);
        $storage = $storage ?? static::getContainer()->get('qti_compiled_deliveries.storage');
        $itemDataService = new GetItemDataService(
            $storage,
            $cache,
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
        );
        return new GetItemService(
            $storage,
            static::getContainer()->get(UrlGenerator::class),
            $this->signedUrlGeneratorRegistry,
            $cache,
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(LtiCustomSettings::class),
            new DeliveryExecutionPropertyService(
                $this->testSessionAccessorFactory,
                $this->getContainer()->get(LtiCustomSettings::class),
                static::getContainer()->get(AssessmentTestSessionFactory::class),
            ),
            static::getContainer()->get(Marshaller::class),
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
            $itemDataService,
            static::getContainer()->get(ItemEnricherInterface::class),
            static::getContainer()->get(LtiTokenResolverInterface::class),
            new TestSessionInitiator(
                new DeliveryExecutionPropertyService(
                    $this->testSessionAccessorFactory,
                    static::getContainer()->get(LtiCustomSettings::class),
                    static::getContainer()->get(AssessmentTestSessionFactory::class),
                ),
                static::getContainer()->get(EventDispatcherInterface::class),
            ),
            new ImageResponseReaderService(
                static::getContainer()->get(LoggerInterface::class),
                static::getContainer()->get(ObjectNormalizer::class),
                $this->attachmentRegistry,
                static::getContainer()->get(Environment::class),
            ),
            static::getContainer()->get(DeliveryExecutionCommentService::class),
        );
    }

    private function mockRequestTokenValue(string $token): void
    {
        static::getContainer()->get('request_stack')->push(
            new Request(
                server: ['HTTP_HOST' => 'test.local', 'HTTP_AUTHORIZATION' => "Bearer $token"],
            ),
        );
    }

    private function tokenStub(): string
    {
        return 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6InByaW1hcnlLZXlQYWlyIn0.eyJleHAiOjE3MTk0NDA3NzgsImlhdCI6MTcxOTMyODY1NywianRpIjoiZDllYTQ3YjYtMWU4YS00YmJkLThkMzUtYzAyZDA5NGZmMjk3IiwiaXNzIjoibWFudWFsX3Njb3JpbmdfZGVsaXZlci0xIiwic3ViIjoiIiwibmFtZSI6Im1hbnVhbFNjb3JpbmdEZWxpdmVyUmVnaXN0cmF0aW9uLTEiLCJhdWQiOiJhdXRoLW9hdC11bml0MDUuZGV2LmdjcC1ldS50YW9jbG91ZC5vcmciLCJ1c2VyIjpudWxsLCJoaWVyYXJjaHkiOm51bGwsImhpZXJhcmNoeUxldmVsIjpudWxsLCJzY29wZXMiOltdLCJyb2xlcyI6W10sInRlbmFudF9pZCI6IjEiLCJsdGlfdG9rZW4iOiJleUpoYkdjaU9pSlNVekkxTmlJc0luUjVjQ0k2SWtwWFZDSXNJbXRwWkNJNkluQnlhVzFoY25sTFpYbFFZV2x5SW4wLmV5Sm9kSFJ3Y3pvdkwzQjFjbXd1YVcxeloyeHZZbUZzTG05eVp5OXpjR1ZqTDJ4MGFTOWpiR0ZwYlM5MlpYSnphVzl1SWpvaU1TNHpMakFpTENKb2RIUndjem92TDNCMWNtd3VhVzF6WjJ4dlltRnNMbTl5Wnk5emNHVmpMMngwYVM5amJHRnBiUzl0WlhOellXZGxYM1I1Y0dVaU9pSk1kR2xTWlhOdmRYSmpaVXhwYm10U1pYRjFaWE4wSWl3aWFIUjBjSE02THk5d2RYSnNMbWx0YzJkc2IySmhiQzV2Y21jdmMzQmxZeTlzZEdrdlkyeGhhVzB2WkdWd2JHOTViV1Z1ZEY5cFpDSTZJakVpTENKb2RIUndjem92TDNCMWNtd3VhVzF6WjJ4dlltRnNMbTl5Wnk5emNHVmpMMngwYVM5amJHRnBiUzkwWVhKblpYUmZiR2x1YTE5MWNta2lPaUpvZEhSd2N6b3ZMM1JsYzNSeWRXNXVaWEl0YjJGMExYVnVhWFF3TlM1a1pYWXVaMk53TFdWMUxuUmhiMk5zYjNWa0xtOXlaeTloY0drdmRqRXZZWFYwYUM5c1lYVnVZMmd0YkhScExURndNeTg0TnpFeU5EVTFORE16WVdFaUxDSm9kSFJ3Y3pvdkwzQjFjbXd1YVcxeloyeHZZbUZzTG05eVp5OXpjR1ZqTDJ4MGFTOWpiR0ZwYlM5eWIyeGxjeUk2V3lKb2RIUndPaTh2Y0hWeWJDNXBiWE5uYkc5aVlXd3ViM0puTDNadlkyRmlMMnhwY3k5Mk1pOXRaVzFpWlhKemFHbHdJMGx1YzNSeWRXTjBiM0lpWFN3aWNtVm5hWE4wY21GMGFXOXVYMmxrSWpvaWJXRnVkV0ZzVTJOdmNtbHVaMFJsYkdsMlpYSlNaV2RwYzNSeVlYUnBiMjR0TVNJc0ltaDBkSEJ6T2k4dmNIVnliQzVwYlhObmJHOWlZV3d1YjNKbkwzTndaV012YkhScEwyTnNZV2x0TDNKbGMyOTFjbU5sWDJ4cGJtc2lPbnNpYVdRaU9pSTROekV5TkRVMU5ETXpZV0VpZlN3aWFIUjBjSE02THk5d2RYSnNMbWx0YzJkc2IySmhiQzV2Y21jdmMzQmxZeTlzZEdrdlkyeGhhVzB2WTNWemRHOXRJanA3SW1SbGJHbDJaWEo1VTJWMGRHbHVaM011YVhSbGJTNXBaQ0k2SW1sMFpXMHRNU0lzSW1SbGJHbDJaWEo1VTJWMGRHbHVaM011Y21WMmFXVjNMbVJsYkdsMlpYSjVSWGhsWTNWMGFXOXVTV1FpT2lKeVpXdGhWSFJ6WlZRak9EY3hNalExTlRRek0yRmhJekprTVRKaVkyUmxaalUzWWpRMVlUSm1NR0ppTldNME56Y3hOak5rT0RJeE1tRTFPRFUzWTJVak1TSXNJbVJsYkdsMlpYSjVVMlYwZEdsdVozTXVjbVYyYVdWM0xtVnVZV0pzWldRaU9uUnlkV1VzSW1SbGJHbDJaWEo1VTJWMGRHbHVaM011Y21WMmFXVjNMbk5vYjNkUmRXVnpkR2x2YmlJNlptRnNjMlVzSW1SbGJHbDJaWEo1VTJWMGRHbHVaM011Ym1GMmFXZGhkR2x2YmlJNkltNXZibVVpTENKa1pXeHBkbVZ5ZVZObGRIUnBibWR6TG5ScGRHeGxjeUk2SWx0ZElpd2laR1ZzYVhabGNubFRaWFIwYVc1bmN5NXlaWFpwWlhjdWMyaHZkMVZ1VTJoMVptWnNaU0k2ZEhKMVpYMHNJbWgwZEhCek9pOHZjSFZ5YkM1cGJYTm5iRzlpWVd3dWIzSm5MM053WldNdmJIUnBMMk5zWVdsdEwyeGhkVzVqYUY5d2NtVnpaVzUwWVhScGIyNGlPbnNpYkc5allXeGxJam9pWlc0dFZWTWlmU3dpYm05dVkyVWlPaUprWkdNd1lqTTNNUzB3TnpGakxUUmlaR1l0WVRFNE5pMDRaV0ZsTWpnNFpUazVOVFlpTENKcWRHa2lPaUptTUdJd1pHWmxOUzFpWVRsa0xUUTVaall0T1Rrek1pMDFPVEppT1RreE5qbGtNVGtpTENKcFlYUWlPakUzTVRrek1qZzJOVFVzSW1WNGNDSTZNVGN4T1RRME1EYzNOeXdpYVhOeklqb2lhSFIwY0hNNkx5OXRjeTFoY0drdGIyRjBMWFZ1YVhRd05TNWtaWFl1WjJOd0xXVjFMblJoYjJOc2IzVmtMbTl5WnlJc0ltRjFaQ0k2SW0xaGJuVmhiRjl6WTI5eWFXNW5YMlJsYkdsMlpYSXRNU0lzSW5OMVlpSTZJbFJsYzNSVVlXdGxjaUo5LmgxYTlwdWVUajE4UUR3ejdWQ1o5eGtfcXoyV0N3QjluWjN1Z1NmUms1SW1XaU5Hcy1IRjNxZXlRN3ZQQ3I5dnNuTm1leURMRkNOaXhobkN3dlZmWUNyQkx5Szh5cXlydllyTmJUb3lkQURRaTRNR1NTM0tOeVI3U3FVUzVXVWN0Y05feEtjMFVnMGszRUFIdmVpeFJWZjlmVEpUU2s2UG9XeUVNRU5OZlh3bG5MaXcwcmhhcFNlelhyakwtMVQwUHczYmxxR0xSZWxiMjJxSnVwajlJd0RicGtxblB4Vm5UdVhHcHBxZkltVFdkNWwzcUtsTUxOY2FsV3JJTVBNeTNZU3pPZUlMUEFYVHBPYjRBRjk4NU1pcFhVZlAwUUl4SEMxMGhXY2ZsUUowQlpOYVJqRU90ajVSZG12ekpBZ2VHR0M5cEgwWG1oTXNLY0kxdDJ1RHNwVHBOb3VjbkJNNExMUGFPYUNqUlJvdVZMRV9iNHFBbUQxVGRMYW9jQ1FnOXB0RHJpYm1JMzhHcU5wQUpRaU9HcEdCYWRZRnhUazZfQlV4NHNYMkRDS2NMdVdwMi14dmg0RWVrMFRGQzBpcGpJejRET0MyQ1BaMWIwTVNnM0RSTHJ1Rk5KbWF0dndIeDhjbVpQUVhpUXJqa1Q5alBtUWllWFF3a3VDYUZ1Nkp0RVRrY0pwWDRDczk5VTluMFFTaldUd0lPaTEtWmxKdlFUOFQtMTEtVGR4ZWEwU1JKc2ZVTktkRXVKR2h6SG5oc3BZaGVuMWx5cWdyczU1bjRUbkk1bXBfWkVaUFhOMEZiOXliTjhJZ2MtUjJDMDJOZkc5QU5haERtQkNDLUtjRHZTWlFDMmlURlg1WGYzZ3BrbThoMW9FVkVWUG1oV3FnMVBzSGFlNVU1bUZBIiwiaHR0cHM6Ly9wdXJsLmltc2dsb2JhbC5vcmcvc3BlYy9sdGkvY2xhaW0vdmVyc2lvbiI6IjEuMy4wIiwiaHR0cHM6Ly9wdXJsLmltc2dsb2JhbC5vcmcvc3BlYy9sdGkvY2xhaW0vbWVzc2FnZV90eXBlIjoiTHRpUmVzb3VyY2VMaW5rUmVxdWVzdCIsImh0dHBzOi8vcHVybC5pbXNnbG9iYWwub3JnL3NwZWMvbHRpL2NsYWltL2RlcGxveW1lbnRfaWQiOiIxIiwiaHR0cHM6Ly9wdXJsLmltc2dsb2JhbC5vcmcvc3BlYy9sdGkvY2xhaW0vdGFyZ2V0X2xpbmtfdXJpIjoiaHR0cHM6Ly90ZXN0cnVubmVyLW9hdC11bml0MDUuZGV2LmdjcC1ldS50YW9jbG91ZC5vcmcvYXBpL3YxL2F1dGgvbGF1bmNoLWx0aS0xcDMvODcxMjQ1NTQzM2FhIiwiaHR0cHM6Ly9wdXJsLmltc2dsb2JhbC5vcmcvc3BlYy9sdGkvY2xhaW0vcm9sZXMiOlsiaHR0cDovL3B1cmwuaW1zZ2xvYmFsLm9yZy92b2NhYi9saXMvdjIvbWVtYmVyc2hpcCNJbnN0cnVjdG9yIl0sInJlZ2lzdHJhdGlvbl9pZCI6Im1hbnVhbFNjb3JpbmdEZWxpdmVyUmVnaXN0cmF0aW9uLTEiLCJodHRwczovL3B1cmwuaW1zZ2xvYmFsLm9yZy9zcGVjL2x0aS9jbGFpbS9yZXNvdXJjZV9saW5rIjp7ImlkIjoiODcxMjQ1NTQzM2FhIn0sImh0dHBzOi8vcHVybC5pbXNnbG9iYWwub3JnL3NwZWMvbHRpL2NsYWltL2N1c3RvbSI6eyJkZWxpdmVyeVNldHRpbmdzLml0ZW0uaWQiOiJpdGVtLTEiLCJkZWxpdmVyeVNldHRpbmdzLnJldmlldy5kZWxpdmVyeUV4ZWN1dGlvbklkIjoicmVrYVR0c2VUIzg3MTI0NTU0MzNhYSMyZDEyYmNkZWY1N2I0NWEyZjBiYjVjNDc3MTYzZDgyMTJhNTg1N2NlIzEiLCJkZWxpdmVyeVNldHRpbmdzLnJldmlldy5lbmFibGVkIjp0cnVlLCJkZWxpdmVyeVNldHRpbmdzLnJldmlldy5zaG93UXVlc3Rpb24iOmZhbHNlLCJkZWxpdmVyeVNldHRpbmdzLm5hdmlnYXRpb24iOiJub25lIiwiZGVsaXZlcnlTZXR0aW5ncy50aXRsZXMiOiJbXSIsImRlbGl2ZXJ5U2V0dGluZ3MucmV2aWV3LnNob3dVblNodWZmbGUiOnRydWV9LCJodHRwczovL3B1cmwuaW1zZ2xvYmFsLm9yZy9zcGVjL2x0aS9jbGFpbS9sYXVuY2hfcHJlc2VudGF0aW9uIjp7ImxvY2FsZSI6ImVuLVVTIn0sIm5vbmNlIjoiZGRjMGIzNzEtMDcxYy00YmRmLWExODYtOGVhZTI4OGU5OTU2IiwibHRpQ2xhaW1zIjp7Imh0dHBzOi8vcHVybC5pbXNnbG9iYWwub3JnL3NwZWMvbHRpL2NsYWltL3ZlcnNpb24iOiIxLjMuMCIsImh0dHBzOi8vcHVybC5pbXNnbG9iYWwub3JnL3NwZWMvbHRpL2NsYWltL21lc3NhZ2VfdHlwZSI6Ikx0aVJlc291cmNlTGlua1JlcXVlc3QiLCJodHRwczovL3B1cmwuaW1zZ2xvYmFsLm9yZy9zcGVjL2x0aS9jbGFpbS9kZXBsb3ltZW50X2lkIjoiMSIsImh0dHBzOi8vcHVybC5pbXNnbG9iYWwub3JnL3NwZWMvbHRpL2NsYWltL3RhcmdldF9saW5rX3VyaSI6Imh0dHBzOi8vdGVzdHJ1bm5lci1vYXQtdW5pdDA1LmRldi5nY3AtZXUudGFvY2xvdWQub3JnL2FwaS92MS9hdXRoL2xhdW5jaC1sdGktMXAzLzg3MTI0NTU0MzNhYSIsImh0dHBzOi8vcHVybC5pbXNnbG9iYWwub3JnL3NwZWMvbHRpL2NsYWltL3JvbGVzIjpbImh0dHA6Ly9wdXJsLmltc2dsb2JhbC5vcmcvdm9jYWIvbGlzL3YyL21lbWJlcnNoaXAjSW5zdHJ1Y3RvciJdLCJyZWdpc3RyYXRpb25faWQiOiJtYW51YWxTY29yaW5nRGVsaXZlclJlZ2lzdHJhdGlvbi0xIiwiaHR0cHM6Ly9wdXJsLmltc2dsb2JhbC5vcmcvc3BlYy9sdGkvY2xhaW0vcmVzb3VyY2VfbGluayI6eyJpZCI6Ijg3MTI0NTU0MzNhYSJ9LCJodHRwczovL3B1cmwuaW1zZ2xvYmFsLm9yZy9zcGVjL2x0aS9jbGFpbS9jdXN0b20iOnsiZGVsaXZlcnlTZXR0aW5ncy5pdGVtLmlkIjoiaXRlbS0xIiwiZGVsaXZlcnlTZXR0aW5ncy5yZXZpZXcuZGVsaXZlcnlFeGVjdXRpb25JZCI6InJla2FUdHNlVCM4NzEyNDU1NDMzYWEjMmQxMmJjZGVmNTdiNDVhMmYwYmI1YzQ3NzE2M2Q4MjEyYTU4NTdjZSMxIiwiZGVsaXZlcnlTZXR0aW5ncy5yZXZpZXcuZW5hYmxlZCI6dHJ1ZSwiZGVsaXZlcnlTZXR0aW5ncy5yZXZpZXcuc2hvd1F1ZXN0aW9uIjpmYWxzZSwiZGVsaXZlcnlTZXR0aW5ncy5uYXZpZ2F0aW9uIjoibm9uZSIsImRlbGl2ZXJ5U2V0dGluZ3MudGl0bGVzIjoiW10iLCJkZWxpdmVyeVNldHRpbmdzLnJldmlldy5zaG93VW5TaHVmZmxlIjp0cnVlfSwiaHR0cHM6Ly9wdXJsLmltc2dsb2JhbC5vcmcvc3BlYy9sdGkvY2xhaW0vbGF1bmNoX3ByZXNlbnRhdGlvbiI6eyJsb2NhbGUiOiJlbi1VUyJ9LCJub25jZSI6ImRkYzBiMzcxLTA3MWMtNGJkZi1hMTg2LThlYWUyODhlOTk1NiIsImp0aSI6ImYwYjBkZmU1LWJhOWQtNDlmNi05OTMyLTU5MmI5OTE2OWQxOSIsImlhdCI6MTcxOTMyODY1NSwiZXhwIjoxNzE5NDQwNzc3LCJpc3MiOiJodHRwczovL21zLWFwaS1vYXQtdW5pdDA1LmRldi5nY3AtZXUudGFvY2xvdWQub3JnIiwiYXVkIjoibWFudWFsX3Njb3JpbmdfZGVsaXZlci0xIiwic3ViIjoiVGVzdFRha2VyIn19.VZi3sUv_Ty2qlD07LRyCoGcxvRZN9yQVwpxYdcMnRR-OvFyIjyMrWXtud7ZYMIeHEzVGhSgBly7BaenVN27iCZ2T06iE0tTFYRNrPtrkdkQ1cVeylpRGa442rUFB5sSe0Pc2AYYCRo-FPHHGQy__HNd7hAVU_bB-JX3txmQOTEni12D2VE7AVzdTMs1nddBurOdC5lNhFZanRa3xMeGlzwRF67drOcFqxVXyMp8GYm4J8iAlwrAtybEArAIwfaHMPXQQ56bJKmRgSY8mZuInuSwJAh--lrnmCqtiJ2QiyjgBk2THzBEHKQYwokgptZ53MhaK3xju18Ai2LltaLcaRLKZLbNf6luxxwgqazWSyIWfApb50aUB2rcanK3U65o28txU9sVzvpk8eJKL0jKfIHTHOFZuH2ddRJwBQTpvNf3f-tylgi82PA44_PWHXQQHlQ1AUb3cLxDRIJoGJzk9lEiDN65uUaiyJOJ5MzOpbHlZFG89UH2wkpUvkNe3seAwnKWXHy8PD9IQ6xeXMZCmVz-jwnKJyuqrTJZEQ656Sl8ak78Xg4l6KjA2-8LUqAWhnypNs8PrAXjmu5nlaAWWn8821MYiXM4tj9NcYB3jZNYpv_dIHYXqE2nYAriIsHJ5jXsfaPslOJJI7hAop7tvUSm1J_JDu6Qlru9mMIOjYGA';
    }
}
