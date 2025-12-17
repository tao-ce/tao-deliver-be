<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Model;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DeliveryExecutionExtraStateDataTest extends TestCase
{
    private const FLAGGED_ITEMS = ['flaggedItem'];
    private const COMMENTS = ['commentedItem' => ['this is a comment']];
    private const TRACE_DATA = [['traceData']];
    private const TOOLS_STATE = ['toolState'];
    private const ITEM_STATE = ['itemIdentifier' => 'ItemState'];
    private const TEMPORARY_ITEM_STATE = ['itemIdentifier' => 'ItemState2'];
    private const UI_EVENTS = [
        [
            'metadata' => [
                'c' => 0,
                'id' => 'recording-1',
                'data' => [
                    'auto' => true,
                    'idx' => 1,
                ],
                'event_name' => 'select',
                'timeStamp' => 1694515268509,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 1,
                'id' => 'P1M813',
                'event_name' => 'start',
                'timeStamp' => 1694515268525,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 2,
                'id' => 'timer.set',
                'data' => 900,
                'event_name' => 'auto',
                'timeStamp' => 1694515268526,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 3,
                'id' => 'play-recoding-btn',
                'data' => [
                    'auto' => true,
                    'idx' => 1,
                ],
                'event_name' => 'click',
                'timeStamp' => 1694515273512,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 4,
                'id' => 'top-nav.next-btn',
                'event_name' => 'click',
                'timeStamp' => 1694515277641,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 5,
                'id' => 'confirm-submit-dialog',
                'event_name' => 'show',
                'timeStamp' => 1694515277642,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 6,
                'id' => 'confirm-submit-dialog.next-btn',
                'event_name' => 'click',
                'timeStamp' => 1694515278708,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 7,
                'id' => 'confirm-submit-dialog',
                'event_name' => 'hide',
                'timeStamp' => 1694515278709,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 8,
                'id' => 'solution-evaluation-dialog',
                'event_name' => 'show',
                'timeStamp' => 1694515278709,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 9,
                'id' => 'P1M813EV',
                'data' => '3',
                'event_name' => 'select',
                'timeStamp' => 1694515279976,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 10,
                'id' => 'solution-evaluation-dialog',
                'event_name' => 'hide',
                'timeStamp' => 1694515280706,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 11,
                'id' => 'solution-dialog',
                'event_name' => 'show',
                'timeStamp' => 1694515280707,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 12,
                'id' => 'top-nav.next-btn',
                'event_name' => 'click',
                'timeStamp' => 1694515286801,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 13,
                'id' => 'solution-dialog',
                'event_name' => 'hide',
                'timeStamp' => 1694515286813,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 14,
                'id' => 'P1M813',
                'event_name' => 'end',
                'timeStamp' => 1694515286813,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'domEventType' => 'custom',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => null,
            'metadata' => [
                'type' => 'move',
                'scope' => 'item',
                'timeStamp' => 1694515286819,
                'direction' => 'next',
                'response' => [
                    'RESPONSE' => [
                        'base' => [
                            'string' => '{"id":"P1M813","ts":1694515286819,"response":[{"id":"P1M813A","type":"multiple_choice","learner_response":""},{"id":"P1M813B","type":"multiple_choice","learner_response":""},{"id":"P1M813EV","type":"multiple_choice","learner_response":"3"}],"common":{"units.DolphinCall.1.timeElapsed":18}}',
                        ],
                    ],
                ],
            ],
        ],
    ];
    private const ASSESSMENT_EVENTS = [
        [
            'actorIdentity' => [
                'id' => '1',
                'name' => 'Test Taker',
                'role' => 'test-taker',
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
                'ip' => '127.0.0.1',
            ],
            'action' => [
                'type' => 'start',
                'status' => 'succeeded',
            ],
            'timestamp' => 1694517414,
            'deliveryExecution' => [
                'id' => 'userId#deliveryId#resultId#tenantId',
                'status' => 'initial',
            ],
            'resourceLink' => [
                'identifier' => '9ec4812a-374c-4f66-b8fc-972c6c6d31b0',
            ],
            'itemId' => 'item-1',
            'reason' => null,
        ],
        [
            'actorIdentity' => [
                'id' => '10',
                'name' => 'Classroom Proctor',
                'role' => 'proctor',
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
                'ip' => '127.0.0.2',
            ],
            'action' => [
                'type' => 'flag',
                'status' => 'succeeded',
            ],
            'timestamp' => 1694517414,
            'deliveryExecution' => [
                'id' => 'userId#deliveryId#resultId#tenantId',
                'status' => 'interacting',
            ],
            'resourceLink' => [
                'identifier' => '9ec4812a-374c-4f66-b8fc-972c6c6d31b0',
            ],
            'itemId' => 'item-2',
            'reason' => [
                'code' => 999,
                'message' => null,
            ],
        ],
    ];

    private const ATTEMPT = 42;

    private DeliveryExecutionExtraStateData $deliveryExecutionExtraStateData;

    public function setUp(): void
    {
        $this->deliveryExecutionExtraStateData = DeliveryExecutionExtraStateData::fromArray([
            'flaggedItems' => self::FLAGGED_ITEMS,
            'comments' => self::COMMENTS,
            'traceData' => self::TRACE_DATA,
            'toolStates' => self::TOOLS_STATE,
            'itemStates' => self::ITEM_STATE,
            'temporaryItemStates' => self::TEMPORARY_ITEM_STATE,
            'uiEvents' => json_encode(self::UI_EVENTS),
            'assessmentEvents' => self::ASSESSMENT_EVENTS,
            'attempt' => self::ATTEMPT,
        ]);
    }
    public function testGetFlaggedItems(): void
    {
        $this->assertSame(self::FLAGGED_ITEMS, $this->deliveryExecutionExtraStateData->getFlaggedItems());
    }

    public function testIsItemFlagged(): void
    {
        $this->assertTrue($this->deliveryExecutionExtraStateData->isItemFlagged('flaggedItem'));
        $this->assertFalse($this->deliveryExecutionExtraStateData->isItemFlagged('nonFlaggedItem'));
    }

    public function testGetComments(): void
    {
        $this->assertSame(self::COMMENTS, $this->deliveryExecutionExtraStateData->getComments());
    }

    public function testGetCommentsForItem(): void
    {
        $this->assertSame(['this is a comment'], $this->deliveryExecutionExtraStateData->getCommentsForItem('commentedItem'));
    }

    public function testGetCommentsForNonExistingItem(): void
    {
        $this->assertSame([], $this->deliveryExecutionExtraStateData->getCommentsForItem('foo'));
    }

    public function testGetTraceData(): void
    {
        $this->assertSame(self::TRACE_DATA, $this->deliveryExecutionExtraStateData->getTraceData());
    }

    public function testGetToolsState(): void
    {
        $this->assertSame(self::TOOLS_STATE, $this->deliveryExecutionExtraStateData->getToolStates());
    }

    public function testGetItemStates(): void
    {
        $this->assertSame(self::ITEM_STATE, $this->deliveryExecutionExtraStateData->getItemStates());
    }

    public function testFindItemState(): void
    {
        $this->assertSame(
            'ItemState',
            $this->deliveryExecutionExtraStateData->getItemState('itemIdentifier'),
        );
    }

    public function testFindItemStateNoIndex(): void
    {
        $this->assertNull(
            $this->deliveryExecutionExtraStateData->getItemState('itemIdentifier444'),
        );
    }

    public function testGetUiEvents(): void
    {
        $this->assertSame(
            self::UI_EVENTS,
            $this->deliveryExecutionExtraStateData->getUiEvents(),
        );
    }

    public function testGetAttempt(): void
    {
        $this->assertSame(
            self::ATTEMPT,
            $this->deliveryExecutionExtraStateData->getAttempt(),
        );
    }

    public function testGetAssessmentEvents(): void
    {
        $this->assertSame(
            self::ASSESSMENT_EVENTS,
            $this->deliveryExecutionExtraStateData->getAssessmentEvents(),
        );
    }

    public function testWithPlagiarismReportItWillOnlyStoreNewestReport(): void
    {
        $plagiarismReports = [
            $this->makePlagiarismReport('2022-02-21T16:37:50+01:00', 'item-1'),
            $this->makePlagiarismReport('2022-02-21T16:37:00+01:00', 'item-2'),
        ];
        $extraStateData = DeliveryExecutionExtraStateData::fromArray([
            'flaggedItems' => self::FLAGGED_ITEMS,
            'comments' => self::COMMENTS,
            'traceData' => self::TRACE_DATA,
            'toolStates' => self::TOOLS_STATE,
            'itemStates' => self::ITEM_STATE,
            'temporaryItemStates' => self::TEMPORARY_ITEM_STATE,
            'plagiarismReports' => $plagiarismReports,
        ]);

        // older
        $testReport = $this->makePlagiarismReport('2022-02-21T00:00:00+01:00', 'item-1');
        $extraStateData = $extraStateData->withPlagiarismReport($testReport);
        $this->assertSame($plagiarismReports, array_values($extraStateData->getPlagiarismReports()));

        // newer
        $testReport = $this->makePlagiarismReport('2022-02-21T16:37:50+00:00', 'item-1');
        $extraStateData = $extraStateData->withPlagiarismReport($testReport);
        $this->assertCount(2, $extraStateData->getPlagiarismReports());
        $this->assertNotSame($plagiarismReports, $extraStateData->getPlagiarismReports());
        // different response
        $testReport = $this->makePlagiarismReport('2022-02-21T16:37:50+00:00', 'item-1', 'RESPONSE_1');
        $extraStateData = $extraStateData->withPlagiarismReport($testReport);
        $this->assertCount(3, $extraStateData->getPlagiarismReports());
        $this->assertNotSame($plagiarismReports, $extraStateData->getPlagiarismReports());
        // different item
        $testReport = $this->makePlagiarismReport('2022-02-21T16:50:00+01:00', 'item-3');
        $extraStateData = $extraStateData->withPlagiarismReport($testReport);
        $this->assertCount(4, $extraStateData->getPlagiarismReports());
    }

    private function makePlagiarismReport($createdAt, $itemId, $responseId = 'RESPONSE')
    {
        return new PlagiarismReport(
            'provider-1',
            'id-' . Uuid::uuid4()->toString(),
            Carbon::parse($createdAt),
            $itemId,
            $responseId,
            'suspicious',
        );
    }
}
