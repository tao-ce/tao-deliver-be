<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Request\ValueResolver\Document;

use App\Domain\Deletable;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryExecutionRepository;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/** @package App\Request\ValueResolver\Document */
class DocumentValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly DocumentManagerInterface $documentManager,
        private readonly DeliveryExecutionRepository $deliveryExecutionRepository,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$this->supports($request, $argument)) {
            return [];
        }

        $documentId = $request->attributes->get('id', '');

        try {
            $document = $this->getDocumentRepository($argument->getType())->find($documentId);

            if ($document instanceof Deletable && $document->isDeleted()) {
                throw new DocumentNotFoundException(sprintf(
                    "Document class '%s' with id '%s' not found",
                    Delivery::class,
                    $document->getId(),
                ));
            }

            return [$document];
        } catch (DocumentNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return is_a($argument->getType(), DocumentInterface::class, true);
    }

    private function getDocumentRepository(string $class): DocumentRepositoryInterface
    {
        return is_a($class, DeliveryExecution::class, true)
            ? $this->deliveryExecutionRepository
            : $this->documentManager->getRepositoryForClass($class);
    }
}
