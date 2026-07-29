<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Model;

use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlAction;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlReason;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Messenger\Message\DeliveryExecution\ExecutionControlMessage;
use App\Messenger\Message\DeliveryExecution\NormalizedExecutionControlMessage;
use App\Messenger\Message\DeliveryExecutionUIEventMessage;
use App\TestRunner\Event\Control\ControlStatus;
use App\TestRunner\Event\Control\ControlType;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use DateTimeInterface;
use LogicException;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryExecutionTest extends KernelTestCase
{
    use DomainTestingTrait;

    private const DEFAULT_DATE = '2020-01-01';

    /** @var DeliveryExecution */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::DEFAULT_DATE));

        $this->subject = $this->createTestDeliveryExecution();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
    }

    public function testItImplementsDocumentInterface(): void
    {
        $this->assertInstanceOf(DocumentInterface::class, $this->subject);
    }

    public function testItCanRetrieveTheId(): void
    {
        $this->assertEquals('userId#deliveryId#resultId#tenantId', $this->subject->getId());
    }

    public function testItCanRetrieveTheDeliveryId(): void
    {
        $this->assertEquals('deliveryId', $this->subject->getDeliveryId());
    }

    public function testItCanRetrieveTheTenantId(): void
    {
        $this->assertEquals('tenantId', $this->subject->getTenantId());
    }

    public function testItHasAStartedDateAtConstruction(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->subject->getStartedAt());
    }

    public function testItDoesNotHaveAFinishedDateAtConstruction(): void
    {
        $this->assertNull($this->subject->getFinishedAt());
    }

    public function testItCanRetrieveTheLtiLaunchParameters(): void
    {
        $this->assertEquals(
            ['ltiLaunchParams', 'result_id' => 'lisResultSourcedId'],
            $this->subject->getLtiLaunchParameters(),
        );

        $this->subject->setLtiLaunchParameters(['ltiLaunchParams1', 'ltiLaunchParams2']);
        $this->assertEquals(['ltiLaunchParams1', 'ltiLaunchParams2'], $this->subject->getLtiLaunchParameters());
    }

    public function testItCanRetrieveBatteryIdLaunchParameter(): void
    {
        $this->subject->setLtiLaunchParameters([]);
        $this->assertNull($this->subject->getBatteryId());

        $batteryId = 'batteryId';
        $this->subject->setLtiLaunchParameters(['battery_id' => $batteryId]);
        $this->assertEquals($batteryId, $this->subject->getBatteryId());
    }

    public function testItThrowsExceptionOnMissingResourceLinkId(): void
    {
        $this->expectException(RuntimeException::class);

        $this->subject->getResourceLink();
    }

    public function testItThrowsExceptionOnNullResourceLinkId(): void
    {
        $this->expectException(RuntimeException::class);

        $this->subject->setLtiLaunchParameters(['resource_link_id' => null]);
        $this->subject->getResourceLink();
    }

    public function testItThrowsExceptionOnEmptyResourceLinkId(): void
    {
        $this->expectException(RuntimeException::class);

        $this->subject->setLtiLaunchParameters(['resource_link_id' => '']);
        $this->subject->getResourceLink();
    }

    public function testItCanRetrieveResourceLinkId(): void
    {
        $resourceLinkId = 'test';

        $this->subject->setLtiLaunchParameters(['resource_link_id' => $resourceLinkId]);
        $this->assertSame(
            $resourceLinkId,
            $this->subject->getResourceLink()->getIdentifier(),
        );
    }

    public function testItThrowsExceptionOnMissingLtiToken(): void
    {
        $this->expectException(RuntimeException::class);

        $this->subject->getLtiToken();
    }

    public function testItThrowsExceptionOnNullLtiToken(): void
    {
        $this->expectException(RuntimeException::class);

        $this->subject->setLtiLaunchParameters(['id_token' => null]);
        $this->subject->getLtiToken();
    }

    public function testItThrowsExceptionOnEmptyLtiToken(): void
    {
        $this->expectException(RuntimeException::class);

        $this->subject->setLtiLaunchParameters(['id_token' => '']);
        $this->subject->getLtiToken();
    }

    public function testItCanRetrieveLtiToken(): void
    {
        $ltiToken = 'test';

        $this->subject->setLtiLaunchParameters(['id_token' => $ltiToken]);
        $this->assertSame(
            $ltiToken,
            $this->subject->getLtiToken(),
        );
    }

    public function testItCanRetrieveTheStatus(): void
    {
        $this->assertEquals(DeliveryExecution::STATUS_INITIAL, $this->subject->getStatus());
    }

    public function testItCanRetrieveTheTestSession(): void
    {
        $this->assertEquals('testSession', $this->subject->getQtiSdkEncodedTestSession());
    }

    public function testItCanRetrieveTheExtraStateData(): void
    {
        $this->assertInstanceOf(DeliveryExecutionExtraStateData::class, $this->subject->getExtraStateData());
        $this->assertEquals([], $this->subject->getExtraStateData()->getComments());
        $this->assertEquals([], $this->subject->getExtraStateData()->getTraceData());
        $this->assertEquals([], $this->subject->getExtraStateData()->getFlaggedItems());
    }

    public function testItCanRetrieveTheLisResultSourcedId(): void
    {
        $this->assertEquals('lisResultSourcedId', $this->subject->getResultId());
    }

    public function testItCanSetAndRetrieveStatus(): void
    {
        $this->assertEquals(DeliveryExecution::STATUS_INITIAL, $this->subject->getStatus());

        $this->subject->setStatus(DeliveryExecution::STATUS_CLOSED);
        $this->assertEquals(DeliveryExecution::STATUS_CLOSED, $this->subject->getStatus());
    }

    public function testItCanSetAndRetrieveQtiSdkEncodedTestSession(): void
    {
        $this->assertEquals('testSession', $this->subject->getQtiSdkEncodedTestSession());

        $this->subject->setQtiSdkEncodedTestSession('someOtherTestSession');
        $this->assertEquals('someOtherTestSession', $this->subject->getQtiSdkEncodedTestSession());
    }

    public function testItCanResetTheFinishDate(): void
    {
        $this->subject->setFinishedAt(Carbon::now());

        $this->subject->resetFinishedAt();

        $this->assertNull($this->subject->getFinishedAt());
    }

    public function testItIncrementsAttempt(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($i, $this->subject->getAttempt());
            $this->subject->close();
        }
    }

    public function testFlag(): void
    {
        $this->assertFalse($this->subject->isFlagged());
        $this->subject->setIsFlagged(true);
        $this->assertTrue($this->subject->isFlagged());
    }

    public function testMultiLanguage(): void
    {
        $this->assertFalse($this->subject->isMultiLanguage());
        $this->subject->setMultiLanguage();
        $this->assertTrue($this->subject->isMultiLanguage());
    }

    public function testMainLocale(): void
    {
        $this->assertNull($this->subject->getMainLocale());
        $this->subject->setMainLocale('en-US');
        $this->assertEquals('en-US', $this->subject->getMainLocale());
    }

    /**
     * @dataProvider getUserSelectedLocaleDataProvider
     */
    public function testGetUserSelectedLocale(
        ?string $expected = null,
        ?string $locale = null,
        ?string $mainLocale = null,
        string $status = DeliveryExecution::STATUS_INITIAL,
        bool $isMultiLanguage = false,
    ): void {
        $this->subject->setStatus($status);
        if ($isMultiLanguage) {
            $this->subject->setMultiLanguage();
        }
        if ($locale) {
            $this->subject->setLocale($locale);
        }
        if ($mainLocale) {
            $this->subject->setMainLocale($mainLocale);
        }

        $this->assertSame($expected, $this->subject->getUserSelectedLocale());
    }

    public function testFlagItem(): void
    {
        $this->subject->flagItem('flaggedItem');
        $this->assertContains('flaggedItem', $this->subject->getExtraStateData()->getFlaggedItems());

        $this->subject->unflagItem('flaggedItem');
        $this->assertNotContains('flaggedItem', $this->subject->getExtraStateData()->getFlaggedItems());
    }

    public function testAddCommentForItem(): void
    {
        $this->subject->addItemComment('foo', 'bar');
        $this->assertSame(['bar'], $this->subject->getExtraStateData()->getComments()['foo']);

        $this->subject->addItemComment('foo', 'baz');
        $this->assertSame(['bar', 'baz'], $this->subject->getExtraStateData()->getComments()['foo']);
    }

    public function testAddTraceData(): void
    {
        $traceData = ['key' => 'value'];

        $this->subject->addTraceData($traceData);

        $this->assertSame(
            [$traceData],
            $this->subject->getExtraStateData()->getTraceData(),
        );
    }

    public function testAddToolState(): void
    {
        $toolState = '{"some": "state"}';

        $this->subject->addToolState($toolState);

        $this->assertEquals(
            [$toolState],
            $this->subject->getExtraStateData()->getToolStates(),
        );
    }

    public function testAddItemState(): void
    {
        $this->subject->clearUpdates();
        $this->subject->addItemState('itemIdentifier123', 'someItemState');
        $this->assertEquals(
            ['itemStates', 'extraStateData', 'reviewInlineComment', 'updatedAt'],
            $this->subject->getUpdates(),
        );

        $this->assertSame(
            ['itemIdentifier123' => 'someItemState'],
            $this->subject->getExtraStateData()->getItemStates(),
        );
    }

    public function testClearDurations(): void
    {
        $this->subject
            ->startServerTimer('id1')
            ->endServerTimer('id1');

        $durationStorage = $this->subject->getExtraStateData()->getDurationStorage();
        $this->subject
            ->startServerTimer('id1')
            ->endServerTimer('id1');
        $this->assertNotEmpty($durationStorage->getServerDurations());
        $this->assertSame($this->subject, $this->subject->clearDurations());
        $durationStorage = $this->subject->getExtraStateData()->getDurationStorage();
        $this->assertEmpty($durationStorage->getServerDurations());
    }

    public function testStartServerTimer(): void
    {
        $this->assertSame($this->subject, $this->subject->startServerTimer('id'));

        $durationStorage = $this->subject->getExtraStateData()->getDurationStorage();

        $this->assertEquals('id', $durationStorage->getServerDurations()[0]->getQtiComponentIdentifier());
        $this->assertNotNull($durationStorage->getServerDurations()[0]->getStartedAt());
        $this->assertNull($durationStorage->getServerDurations()[0]->getEndedAt());
        $this->assertEquals(0.0, $durationStorage->getServerDurations()[0]->getDuration());
    }

    public function testEndServerTimer(): void
    {
        $this->assertSame($this->subject, $this->subject->startServerTimer('id'));
        $this->assertSame($this->subject, $this->subject->endServerTimer('id'));

        $durationStorage = $this->subject->getExtraStateData()->getDurationStorage();

        $this->assertEquals('id', $durationStorage->getServerDurations()[0]->getQtiComponentIdentifier());
        $this->assertNotNull($durationStorage->getServerDurations()[0]->getStartedAt());
        $this->assertNotNull($durationStorage->getServerDurations()[0]->getEndedAt());
        $this->assertGreaterThan(0.0, $durationStorage->getServerDurations()[0]->getDuration());
    }

    public function testEndServerTimerWhenNotStarted(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot end server timer as it was not started yet: id');

        $this->subject->endServerTimer('id');
    }

    public function testEndServerTimerWhenAlreadyEnded(): void
    {
        $this->assertSame($this->subject, $this->subject->startServerTimer('id'));
        $this->assertSame($this->subject, $this->subject->endServerTimer('id'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot end server timer as it ended already: id');

        $this->subject->endServerTimer('id');
    }

    public function testGetServerDuration(): void
    {
        $this->subject
            ->startServerTimer('id1')
            ->endServerTimer('id1')
            ->startServerTimer('id2');

        $durationStorage = $this->subject->getExtraStateData()->getDurationStorage();

        $this->assertNotNull($durationStorage->getServerDuration('id1'));
        $this->assertEquals(0.0, $durationStorage->getServerDuration('id2'));
        $this->assertEquals(0.0, $durationStorage->getServerDuration('id3'));
    }

    public function testItSetsUpdatedAt(): void
    {
        $this->assertEquals(
            Carbon::now(),
            $this->subject->getUpdatedAt(),
        );
    }

    public function testItDoesNotUpdateUpdatedAt(): void
    {
        Carbon::setTestNow('2023-07-05');

        $this->subject->clearUpdates();
        $this->subject->getUpdates();

        $this->assertEquals(
            Carbon::parse(self::DEFAULT_DATE),
            $this->subject->getUpdatedAt(),
        );
    }

    public function testItUpdatesUpdatedAt(): void
    {
        Carbon::setTestNow('2023-07-05');

        $this->subject->clearUpdates();
        $this->subject->start();
        $this->subject->getUpdates();

        $this->assertEquals(
            Carbon::now(),
            $this->subject->getUpdatedAt(),
        );
    }

    public function testItKeepsInitialStartedAtImmutable(): void
    {
        Carbon::setTestNow();

        $startedAt = clone $this->subject->getStartedAt();
        $this->subject->start();
        $this->assertNotEquals($startedAt, $this->subject->getStartedAt());
        $this->assertSame($startedAt->getTimestamp(), $this->subject->getInitialStartTimestamp());
    }

    public function testItHasNoUiEventsByDefault(): void
    {
        $this->assertFalse($this->subject->hasUiEvents());
    }

    public function testItAddsUiEvents(): void
    {
        $locale = 'en-US';
        $this->subject->setLocale($locale);
        $deliveryExecution = $this->createTestDeliveryExecution(locale: $locale);
        $uiEventMessages = [
            new DeliveryExecutionUIEventMessage($deliveryExecution, [['event 1', 'event 2']]),
            new DeliveryExecutionUIEventMessage($deliveryExecution, [['event 3']]),
        ];

        foreach ($uiEventMessages as $uiEventMessage) {
            $this->subject->pushUiEvents($uiEventMessage);
        }

        $this->assertEquals(
            new DeliveryExecutionUIEventMessage(
                $deliveryExecution,
                array_merge(
                    ...array_map(
                        static fn(DeliveryExecutionUIEventMessage $message) => $message->getEvents(),
                        $uiEventMessages,
                    ),
                ),
            ),
            $this->subject->popAllUiEvents(),
        );
        $this->assertFalse($this->subject->hasUiEvents());
    }

    public function testItHasNoAssessmentEventsByDefault(): void
    {
        $this->assertFalse($this->subject->hasAssessmentEvents());
    }

    public function testItHasNoInlineCommentsByDefault(): void
    {
        $this->assertNull($this->subject->getReviewInlineComment());
    }

    public function testItCanAddInlineComments(): void
    {
        $itemId = 'item-1';
        $comment = ['comment' => 'value'];
        $this->subject->addReviewInlineComment('scorer-1', $itemId, $comment);

        $this->assertSame(
            $comment,
            $this->subject->getReviewInlineComment()->getFeedback($itemId),
        );
    }

    public function testItAppendsUnassignedComments(): void
    {
        $itemId = 'item-1';
        $feedbacks = new InlineFeedbackCollection(
            [
                $itemId => [
                    'responses' => [
                        'RESPONSE' => [
                            'highlights' => [
                                [
                                    'c' => 'tao--g8swgzho',
                                    'path2' => [
                                        4,
                                        -1,
                                    ],
                                    'groupId' => '1',
                                    'textLength' => 1,
                                    'offsetBefore' => 0,
                                ],
                            ],
                            'comments' => [
                                'tao--g8swgzho' => 'feedback 1',
                            ],
                        ],
                    ],
                    'feedbackOwners' => [
                        'scorer-1' => [
                            'responses' => [
                                'RESPONSE' => [
                                    'highlights' => [
                                        [
                                            'c' => 'tao--p2qxnn26',
                                            'path2' => [
                                                0,
                                                -1,
                                            ],
                                            'groupId' => '1',
                                            'textLength' => 1,
                                            'offsetBefore' => 10,
                                        ],
                                    ],
                                    'comments' => [
                                        'tao--p2qxnn26' => 'feedback 2',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $this->assertSame(
            [
                'responses' => [
                    'RESPONSE' => [
                        'highlights' => [
                            [
                                'c' => 'tao--p2qxnn26',
                                'path2' => [
                                    0,
                                    -1,
                                ],
                                'groupId' => '1',
                                'textLength' => 1,
                                'offsetBefore' => 10,
                            ],
                            [
                                'c' => 'tao--g8swgzho',
                                'path2' => [
                                    4,
                                    -1,
                                ],
                                'groupId' => '1',
                                'textLength' => 1,
                                'offsetBefore' => 0,
                            ],
                        ],
                        'comments' => [
                            'tao--p2qxnn26' => 'feedback 2',
                            'tao--g8swgzho' => 'feedback 1',
                        ],
                    ],
                ],
            ],
            $feedbacks->getOwnerFeedback('scorer-1', $itemId),
        );
        $this->assertSame(
            [
                'responses' => [
                    'RESPONSE' => [
                        'highlights' => [
                            [
                                'c' => 'tao--g8swgzho',
                                'path2' => [
                                    4,
                                    -1,
                                ],
                                'groupId' => '1',
                                'textLength' => 1,
                                'offsetBefore' => 0,
                            ],
                        ],
                        'comments' => [
                            'tao--g8swgzho' => 'feedback 1',
                        ],
                    ],
                ],
            ],
            $feedbacks->getOwnerFeedback('scorer-2', $itemId),
        );
    }

    public function testItSegregatesInlineComments(): void
    {
        $itemId = 'item-1';
        $feedback1 = [
            'responses' => [
                'RESPONSE' => [
                    'highlights' => [
                        [
                            'c' => 'tao--g8swgzho',
                            'path2' => [
                                4,
                                -1,
                            ],
                            'groupId' => '1',
                            'textLength' => 1,
                            'offsetBefore' => 0,
                        ],
                    ],
                    'comments' => [
                        'tao--g8swgzho' => 'feedback 1',
                    ],
                ],
            ],
        ];
        $feedback2 = [
            'responses' => [
                'RESPONSE' => [
                    'highlights' => [
                        [
                            'c' => 'tao--p2qxnn26',
                            'path2' => [
                                0,
                                -1,
                            ],
                            'groupId' => '1',
                            'textLength' => 1,
                            'offsetBefore' => 10,
                        ],
                    ],
                    'comments' => [
                        'tao--p2qxnn26' => 'feedback 2',
                    ],
                ],
            ],
        ];
        $this->subject->addReviewInlineComment('scorer-1', $itemId, $feedback1);
        $this->subject->addReviewInlineComment('scorer-2', $itemId, $feedback2);

        $this->assertSame(
            $feedback1,
            $this->subject->getReviewInlineComment()->getOwnerFeedback('scorer-1', $itemId),
        );
        $this->assertSame(
            $feedback2,
            $this->subject->getReviewInlineComment()->getOwnerFeedback('scorer-2', $itemId),
        );
        $this->assertSame(
            [
                'responses' => [
                    'RESPONSE' => [
                        'highlights' => [
                            [
                                'c' => 'tao--g8swgzho',
                                'path2' => [
                                    4,
                                    -1,
                                ],
                                'groupId' => '1',
                                'textLength' => 1,
                                'offsetBefore' => 0,
                            ],
                            [
                                'c' => 'tao--p2qxnn26',
                                'path2' => [
                                    0,
                                    -1,
                                ],
                                'groupId' => '1',
                                'textLength' => 1,
                                'offsetBefore' => 10,
                            ],
                        ],
                        'comments' => [
                            'tao--g8swgzho' => 'feedback 1',
                            'tao--p2qxnn26' => 'feedback 2',
                        ],
                    ],
                ],
            ],
            $this->subject->getReviewInlineComment()->getFeedback($itemId),
        );
    }

    public function testItResetsInlineCommentUponChangingResponse(): void
    {
        $itemId1 = 'item-1';
        $comment1 = ['comment' => 'value1'];
        $itemId2 = 'item-2';
        $comment2 = ['comment' => 'value2'];
        $this->subject->addReviewInlineComment('scorer-1', $itemId1, $comment1);
        $this->subject->addReviewInlineComment('scorer-2', $itemId2, $comment2);
        $this->subject->addItemState($itemId1, '');

        $this->assertEmpty(
            $this->subject->getReviewInlineComment()->getFeedback($itemId1),
        );
        $this->assertSame(
            $comment2,
            $this->subject->getReviewInlineComment()->getFeedback($itemId2),
        );
    }

    public function testItClearsAllCommentsUponClearingResponses(): void
    {
        $itemId = 'item-1';
        $comment = ['comment' => 'value'];
        $this->subject->addReviewInlineComment('scorer-1', $itemId, $comment);
        $this->subject->clearAllItemState();

        $this->assertNull($this->subject->getReviewInlineComment());
    }

    public function testItAddsAssessmentEvents(): void
    {
        /** @var NormalizerInterface $normalizer */
        $normalizer = self::getContainer()->get(NormalizerInterface::class);

        $normalizedAssessmentEventMessages = [
            $normalizer->normalize(
                new ExecutionControlMessage(
                    new DeliveryExecutionActorIdentity(
                        '1',
                        'Test Taker',
                        DeliveryExecutionActorRole::ROLE_TEST_TAKER,
                        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
                        '127.0.0.1',
                    ),
                    new DeliveryExecutionControlAction(
                        ControlType::START,
                        ControlStatus::SUCCESS,
                    ),
                    Carbon::now(),
                    $this->createTestDeliveryExecution(),
                    'item-1',
                    null,
                ),
            ),
            $normalizer->normalize(
                new ExecutionControlMessage(
                    new DeliveryExecutionActorIdentity(
                        '10',
                        'Classroom Proctor',
                        DeliveryExecutionActorRole::ROLE_PROCTOR,
                        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
                        '127.0.0.2',
                    ),
                    new DeliveryExecutionControlAction(
                        ControlType::FLAG,
                        ControlStatus::SUCCESS,
                    ),
                    Carbon::now(),
                    $this->createTestDeliveryExecution(status: DeliveryExecution::STATUS_INTERACTING),
                    'item-2',
                    new DeliveryExecutionControlReason(''),
                ),
            ),
        ];

        foreach ($normalizedAssessmentEventMessages as $normalizedAssessmentEventMessage) {
            $this->subject->pushAssessmentEvent($normalizedAssessmentEventMessage);
        }

        $this->assertEquals(
            array_map(
                fn(
                    array $normalizedAssessmentEventMessage,
                ): NormalizedExecutionControlMessage => new NormalizedExecutionControlMessage(
                    $this->subject,
                    $normalizedAssessmentEventMessage,
                ),
                $normalizedAssessmentEventMessages,
            ),
            iterator_to_array($this->subject->popAllAssessmentEvents()),
        );
        $this->assertFalse($this->subject->hasAssessmentEvents());
    }

    public function testReopen(): void
    {
        $originalSession = $this->subject->getQtiSdkEncodedTestSession();
        $this->subject->preserveOriginalSession();
        $this->subject->setFinishedAt(Carbon::now());

        $this->assertEquals(Carbon::now(), $this->subject->getFinishedAt());

        $this->assertSame(
            $originalSession,
            $this->subject->getExtraStateData()->getOriginalSession(),
        );

        $this->subject->setQtiSdkEncodedTestSession('another session');
        $this->subject->preserveOriginalSession();
        $this->assertNotEquals(
            $originalSession,
            $this->subject->getQtiSdkEncodedTestSession(),
        );

        $itemId = 'item-1';
        $this->subject->markItemAsExternalScored($itemId);

        $this->assertTrue($this->subject->isItemScoredExternally($itemId));

        $this->subject->reopen();
        $this->assertNull($this->subject->getExtraStateData()->getOriginalSession());
        $this->assertNull($this->subject->getFinishedAt());
        $this->assertSame(
            $originalSession,
            $this->subject->getQtiSdkEncodedTestSession(),
        );
        $this->assertFalse($this->subject->isItemScoredExternally($itemId));
    }

    public function testItCanSetAndGetLocale(): void
    {
        $this->assertNull($this->subject->getLocale());

        $this->subject->setLocale('en-GB');
        $this->assertEquals('en-GB', $this->subject->getLocale());
        $this->assertTrue($this->subject->isMultiLanguage());
    }

    public function testItAssignsLocaleViaConstructor(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(locale: 'fr-FR');
        $this->assertEquals('fr-FR', $deliveryExecution->getLocale());
    }

    public function testDoesBelongToBattery(): void
    {
        $deliveryExecution1 = $this->createTestDeliveryExecution(ltiLaunchParameters: ['battery_id' => 'battery-id']);

        $this->assertTrue($deliveryExecution1->doesBelongToBattery());

        $deliveryExecution2 = $this->createTestDeliveryExecution();

        $this->assertFalse($deliveryExecution2->doesBelongToBattery());
    }

    public function testItCanAddMarkingSymbolsToInlineComments(): void
    {
        $itemId = 'item-with-symbols';
        $scorerId = 'scorer-1';

        $feedbackPayload = [
            'responses' => [
                'RESPONSE' => [
                    'markingSymbols' => [
                        [
                            'c' => 'symbol-unique-id-1',
                            'path2' => [0, -1],
                            'groupId' => '1',
                            'offsetBefore' => 5,
                            'shapeId' => 'circle',
                            'color' => '#ff0000',
                            'label' => 'Grammar Error',
                        ],
                        [
                            'c' => 'symbol-unique-id-2',
                            'path2' => [0, -1],
                            'groupId' => '1',
                            'offsetBefore' => 10,
                            'shapeId' => 'rectangle',
                            'color' => '#00ff00',
                            'label' => 'Good Point',
                        ],
                    ],
                    'comments' => [
                        'symbol-unique-id-1' => 'Please fix this.',
                        'symbol-unique-id-2' => 'Well done.',
                    ],
                ],
            ],
        ];

        $this->subject->addReviewInlineComment($scorerId, $itemId, $feedbackPayload);

        $storedFeedback = $this->subject->getReviewInlineComment()->getFeedback($itemId);

        $this->assertArrayHasKey('responses', $storedFeedback);
        $this->assertArrayHasKey('markingSymbols', $storedFeedback['responses']['RESPONSE']);

        $symbols = $storedFeedback['responses']['RESPONSE']['markingSymbols'];
        $this->assertCount(2, $symbols);

        $this->assertEquals('symbol-unique-id-1', $symbols[0]['c']);
        $this->assertEquals('circle', $symbols[0]['shapeId']);
        $this->assertEquals('#ff0000', $symbols[0]['color']);
        $this->assertEquals('Grammar Error', $symbols[0]['label']);

        $this->assertEquals('symbol-unique-id-2', $symbols[1]['c']);
        $this->assertEquals('rectangle', $symbols[1]['shapeId']);
        $this->assertEquals('#00ff00', $symbols[1]['color']);
        $this->assertEquals('Good Point', $symbols[1]['label']);
    }

    public function getUserSelectedLocaleDataProvider(): array
    {
        return [
            'Initial State' => [],
            'Defined locale in initial status' => [
                'expected' => null,
                'locale' => 'en-GB',
            ],
            'Defined locale' => [
                'expected' => 'en-GB',
                'locale' => 'en-GB',
                'mainLocale' => null,
                'status' => DeliveryExecution::STATUS_INTERACTING,
            ],
            'Main locale only in initial status' => [
                'expected' => null,
                'locale' => null,
                'mainLocale' => 'en-GB',
            ],
            'Main locale only' => [
                'expected' => 'en-GB',
                'locale' => null,
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INTERACTING,
            ],
            'Both locales in initial status' => [
                'expected' => null,
                'locale' => 'en-US',
                'mainLocale' => 'en-GB',
            ],
            'Both locales' => [
                'expected' => 'en-US',
                'locale' => 'en-US',
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INTERACTING,
            ],
            'Main locale as multi-lang in initial status' => [
                'expected' => null,
                'locale' => null,
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INITIAL,
                'isMultiLanguage' => true,
            ],
            'Main locale as multi-lang' => [
                'expected' => 'en-GB',
                'locale' => null,
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INTERACTING,
                'isMultiLanguage' => true,
            ],
        ];
    }

    /**
     * @dataProvider getUserSelectedLocaleForMultiLanguageDataProvider
     */
    public function testGetUserSelectedLocaleForMultiLanguage(
        ?string $expected,
        ?string $locale,
        ?string $mainLocale,
        string $status,
        bool $isMultiLanguage,
    ): void {
        $this->subject->setStatus($status);
        if ($isMultiLanguage) {
            $this->subject->setMultiLanguage();
        }
        if ($locale) {
            $this->subject->setLocale($locale);
        }
        if ($mainLocale) {
            $this->subject->setMainLocale($mainLocale);
        }

        $this->assertSame($expected, $this->subject->getUserSelectedLocaleForMultiLanguage());
    }

    public function getUserSelectedLocaleForMultiLanguageDataProvider(): array
    {
        return [
            'Single-language returns null' => [
                'expected' => null,
                'locale' => null,
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INTERACTING,
                'isMultiLanguage' => false,
            ],
            'Multi-language returns mainLocale' => [
                'expected' => 'en-GB',
                'locale' => null,
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INTERACTING,
                'isMultiLanguage' => true,
            ],
            'Multi-language returns user selected locale' => [
                'expected' => 'ja-JP',
                'locale' => 'ja-JP',
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INTERACTING,
                'isMultiLanguage' => true,
            ],
            'Multi-language in initial state returns null' => [
                'expected' => null,
                'locale' => 'ja-JP',
                'mainLocale' => 'en-GB',
                'status' => DeliveryExecution::STATUS_INITIAL,
                'isMultiLanguage' => true,
            ],
        ];
    }
}
