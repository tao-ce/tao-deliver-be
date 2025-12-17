<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Delivery\Event\DeliveryCreatedEvent;
use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use App\Responder\SerializerResponder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublishDeliveryCloneAction
{
    public function __construct(
        private readonly DeliveryRepository $repository,
        private EventDispatcherInterface $eventDispatcher,
        private readonly SerializerResponder $responder,
    ) {
    }

    public function __invoke(Request $request, Delivery $delivery, string $tenantId): JsonResponse
    {
        if ($delivery->getTenantId() !== $tenantId || !$delivery->getDraftId()) {
            // Returning a 404 here in order not to expose Delivery IDs to other tenants
            throw new NotFoundHttpException(
                sprintf(
                    'Document class \'%s\' with id \'%s\' not found',
                    $delivery::class,
                    $delivery->getId(),
                ),
            );
        }

        $draftDelivery = $this->repository->findUnrestricted($delivery->getDraftId())->setIsDeleted(false);
        $this->repository->save($delivery->setDraftId(null));
        $this->repository->save($draftDelivery);
        $this->eventDispatcher->dispatch(new DeliveryCreatedEvent($draftDelivery));

        return $this->responder->createJsonResponse(['data' => $draftDelivery]);
    }
}
