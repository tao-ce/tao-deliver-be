<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Request\ValueResolver\Document;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\EnvironmentManagementClient\Http\LtiMessageExtractorInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class DeliveryValueResolver implements ValueResolverInterface
{
    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private LtiMessageExtractorInterface $ltiMessageExtractor,
        private HttpMessageFactoryInterface $psrHttpFactory,
    ) {
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return is_a($argument->getType(), Delivery::class, true);
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$this->supports($request, $argument)) {
            return [];
        }

        try {
            $document = $this->deliveryRepository
                ->find($request->attributes->get('id', ''));

            $this->validateDeliveryTenant($document, $request);

            return [$document];
        } catch (DocumentNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }

    /**
     * @throws AccessDeniedHttpException
     */
    private function validateDeliveryTenant(Delivery $delivery, Request $request): void
    {
        $psrRequest = $this->psrHttpFactory->createRequest($request);

        $ltiMessagePayload = $this->ltiMessageExtractor->extract($psrRequest);
        $token = $ltiMessagePayload->getToken();
        $tenantId = $token->getClaims()->get('tenant_id');

        if ($tenantId !== $delivery->getTenantId()) {
            throw new AccessDeniedHttpException(sprintf('You can not get access to delivery ID %s', $delivery->getId()));
        }
    }
}
