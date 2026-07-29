<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;

trait AnnotationCommentTrait
{
    private ?InlineFeedbackCollection $annotationComments = null;

    public function hasAnnotationComment(): bool
    {
        return $this->annotationComments !== null && !empty($this->annotationComments->toArray());
    }

    public function getAnnotationComments(): InlineFeedbackCollection
    {
        return $this->annotationComments ?? new InlineFeedbackCollection();
    }

    public function updateAnnotationComments(?InlineFeedbackCollection $comments = null): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->annotationComments = $comments;

        return $deliveryExecutionExtraStateData;
    }

    public function hydrateAnnotationCommentFromArray(?array $annotationComments): ?InlineFeedbackCollection
    {
        if ($annotationComments) {
            $this->annotationComments = new InlineFeedbackCollection($annotationComments);
            return $this->annotationComments;
        }

        return null;
    }

    public function withAnnotationComment(?string $owner, string $itemIdentifier, array $comment): self
    {
        $deliveryExecutionExtraStateData = clone $this;

        $currentCollection = $deliveryExecutionExtraStateData->annotationComments ?? new InlineFeedbackCollection();
        $deliveryExecutionExtraStateData->annotationComments = clone $currentCollection;

        if ($owner === null) {
            $deliveryExecutionExtraStateData->annotationComments->addFeedback($itemIdentifier, $comment);
        } else {
            $deliveryExecutionExtraStateData->annotationComments->addOwnerFeedback($owner, $itemIdentifier, $comment);
        }

        return $deliveryExecutionExtraStateData;
    }

    public function getItemAnnotationComment(?string $owner, string $itemIdentifier): array
    {
        $collection = $this->annotationComments ?? new InlineFeedbackCollection();

        if ($owner === null) {
            return $collection->getFeedback($itemIdentifier);
        }

        return $collection->getOwnerFeedback($owner, $itemIdentifier);
    }

    public function removeAnnotationComment(string $itemIdentifier): self
    {
        $deliveryExecutionExtraStateData = clone $this;

        $currentCollection = $deliveryExecutionExtraStateData->annotationComments ?? new InlineFeedbackCollection();
        $deliveryExecutionExtraStateData->annotationComments = clone $currentCollection;

        $deliveryExecutionExtraStateData->annotationComments->removeFeedback($itemIdentifier);

        return $deliveryExecutionExtraStateData;
    }
}
