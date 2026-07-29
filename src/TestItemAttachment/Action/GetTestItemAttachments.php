<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestItemAttachment\Action;

use App\Domain\Delivery\Model\Delivery;
use App\Responder\SerializerResponder;
use App\TestItemAttachment\Service\ItemCategoryBasedAttachmentRegistry;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use qtism\runtime\tests\RouteItem;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class GetTestItemAttachments
{
    public function __construct(
        private TestSessionAccessorFactory $testSessionAccessorFactory,
        private ItemCategoryBasedAttachmentRegistry $attachmentRegistry,
        private SerializerResponder $responder,
    ) {
    }

    public function __invoke(Delivery $delivery): JsonResponse
    {
        $testSession = $this->testSessionAccessorFactory->create($delivery)->instantiate();
        $response = ['items' => []];
        $itemsResponse = &$response['items'];
        $attachments = $this->attachmentRegistry->resolveAttachments(
            $delivery->getTenantId(),
            $testSession->getRoute()->getCategories()->getArrayCopy(),
        );
        /** @var RouteItem $routeItem */
        foreach ($testSession->getRoute()->getAllRouteItems() as $routeItem) {
            $itemCategories = $routeItem->getAssessmentItemRef()->getCategories()->getArrayCopy();
            $itemAttachments = array_intersect_key($attachments, array_flip($itemCategories));
            if (empty($itemAttachments)) {
                continue;
            }
            $itemsResponse[] = [
                'itemId' => $routeItem->getAssessmentItemRef()->getIdentifier(),
                'attachments' => array_values($itemAttachments),
            ];
        }

        return $this->responder->createJsonResponse($response);
    }
}
