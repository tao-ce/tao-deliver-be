<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Model\ExtraStateData;

use App\Domain\DeliveryExecution\Model\ExtraStateData\SubData\AnnotationCommentTrait;
use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;
use PHPUnit\Framework\TestCase;

class AnnotationCommentTraitTest extends TestCase
{
    private object $traitConsumer;

    protected function setUp(): void
    {
        $this->traitConsumer = new class {
            use AnnotationCommentTrait;

            // Helper to inspect private property for testing mutation
            public function getInternalCollectionProperty(): ?InlineFeedbackCollection
            {
                return $this->annotationComments;
            }
        };
    }

    public function testHasAnnotationCommentReturnsFalseInitially(): void
    {
        $this->assertFalse($this->traitConsumer->hasAnnotationComment());
    }

    public function testGetAnnotationCommentsReturnsEmptyCollectionInitially(): void
    {
        $collection = $this->traitConsumer->getAnnotationComments();

        $this->assertInstanceOf(InlineFeedbackCollection::class, $collection);
    }

    public function testUpdateAnnotationCommentsIsImmutable(): void
    {
        $collection = $this->createMock(InlineFeedbackCollection::class);

        $newConsumer = $this->traitConsumer->updateAnnotationComments($collection);

        $this->assertNotSame($this->traitConsumer, $newConsumer);
        $this->assertFalse($this->traitConsumer->hasAnnotationComment());
        $this->assertSame($collection, $newConsumer->getAnnotationComments());
    }

    public function testWithAnnotationCommentWithoutOwner(): void
    {
        $itemIdentifier = 'item_1';
        $comment = ['text' => 'Good job'];

        $newConsumer = $this->traitConsumer->withAnnotationComment(null, $itemIdentifier, $comment);

        $this->assertNotSame($this->traitConsumer, $newConsumer);

        $collection = $newConsumer->getAnnotationComments();
        $this->assertInstanceOf(InlineFeedbackCollection::class, $collection);
    }

    public function testWithAnnotationCommentWithOwner(): void
    {
        $owner = 'reviewer_1';
        $itemIdentifier = 'item_1';
        $comment = ['text' => 'Needs work'];

        $newConsumer = $this->traitConsumer->withAnnotationComment($owner, $itemIdentifier, $comment);

        $this->assertNotSame($this->traitConsumer, $newConsumer);
        $this->assertInstanceOf(InlineFeedbackCollection::class, $newConsumer->getAnnotationComments());
    }

    public function testGetItemAnnotationCommentWithoutOwner(): void
    {
        // Setup initial state using updateAnnotationComments to inject a mock
        $mockCollection = $this->createMock(InlineFeedbackCollection::class);
        $mockCollection->expects($this->once())
            ->method('getFeedback')
            ->with('item_1')
            ->willReturn(['feedback_data']);

        $configuredConsumer = $this->traitConsumer->updateAnnotationComments($mockCollection);

        $result = $configuredConsumer->getItemAnnotationComment(null, 'item_1');

        $this->assertSame(['feedback_data'], $result);
    }

    public function testGetItemAnnotationCommentWithOwner(): void
    {
        $mockCollection = $this->createMock(InlineFeedbackCollection::class);
        $mockCollection->expects($this->once())
            ->method('getOwnerFeedback')
            ->with('owner_1', 'item_1')
            ->willReturn(['owner_feedback']);

        $configuredConsumer = $this->traitConsumer->updateAnnotationComments($mockCollection);

        $result = $configuredConsumer->getItemAnnotationComment('owner_1', 'item_1');

        $this->assertSame(['owner_feedback'], $result);
    }

    public function testHydrateAnnotationCommentFromArrayMutatesState(): void
    {
        $data = ['item-1' => ['feedback']];

        // Act: Call the hydration method
        $result = $this->traitConsumer->hydrateAnnotationCommentFromArray($data);

        // Assert: Return value is correct
        $this->assertInstanceOf(InlineFeedbackCollection::class, $result);

        // Assert: Internal state was mutated
        $this->assertTrue($this->traitConsumer->hasAnnotationComment());
        $this->assertEquals($result, $this->traitConsumer->getInternalCollectionProperty());
    }

    public function testHydrateAnnotationCommentFromArrayReturnsNullOnEmpty(): void
    {
        $result = $this->traitConsumer->hydrateAnnotationCommentFromArray(null);

        $this->assertNull($result);
        $this->assertFalse($this->traitConsumer->hasAnnotationComment());
    }
}
