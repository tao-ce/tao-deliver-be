<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use App\Responder\SerializerResponder;
use App\Service\Delivery\UpdateDeliveryService;
use App\Validator\Delivery\UpdateDeliveryRequestValidator;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UpdateDeliveryAction
{
    /** @var SerializerResponder */
    private $responder;

    /** @var UpdateDeliveryRequestValidator */
    private $validator;

    /** @var UpdateDeliveryService */
    private $updateDeliveryService;

    /** @var DeliveryRepository */
    private $repository;

    public function __construct(
        SerializerResponder $responder,
        UpdateDeliveryRequestValidator $validator,
        DeliveryRepository $repository,
        UpdateDeliveryService $updateDeliveryService,
    ) {
        $this->responder = $responder;
        $this->validator = $validator;
        $this->updateDeliveryService = $updateDeliveryService;
        $this->repository = $repository;
    }

    public function __invoke(Request $request, string $id)
    {
        $configuration = $this->validator->getValidatedRequestParameter($request, 'configuration', []);

        $configuration['status'] = $configuration['status'] ?? true;

        try {
            /** @var Delivery $delivery */
            $delivery = $this->repository->find($id);
        } catch (DocumentNotFoundException $exception) {
            throw new NotFoundHttpException(sprintf('Delivery id %s not found', $id), $exception);
        }

        $delivery = $this->updateDeliveryService->update($delivery, $configuration);

        return $this->responder->createJsonResponse(['data' => $delivery]);
    }
}
