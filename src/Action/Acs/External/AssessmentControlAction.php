<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Acs\External;

use App\Repository\DeliveryExecutionAlias\Contract\DeliveryExecutionIdentifierAliasRepositoryInterface;
use App\Service\AssessmentControl\AssessmentControlProcessor;
use App\Service\AssessmentControl\Exception\NotControllableDeliveryExecutionException;
use App\Service\AssessmentControl\Exception\NotSupportedAssessmentControlAction;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\EnvironmentManagementClient\Http\BearerJWTTokenExtractor;
use OAT\Library\EnvironmentManagementClient\Http\JWTTokenExtractorInterface;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Proctoring\Serializer\AcsControlResultSerializer;
use OAT\Library\Lti1p3Proctoring\Serializer\AcsControlResultSerializerInterface;
use OAT\Library\Lti1p3Proctoring\Serializer\AcsControlSerializer;
use OAT\Library\Lti1p3Proctoring\Serializer\AcsControlSerializerInterface;
use OAT\Library\Lti1p3Proctoring\Service\AcsServiceInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssessmentControlAction
{
    private const REQUIRED_SCOPE = 'proctor:external-access';

    public function __construct(
        private readonly AssessmentControlProcessor $assessmentControlProcessor,
        private readonly DeliveryExecutionIdentifierAliasRepositoryInterface $deliveryExecutionIdentifierAliasRepository,
        private readonly DeliveryExecutionService $deliveryExecutionService,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly AcsControlSerializerInterface $controlSerializer = new AcsControlSerializer(),
        private readonly AcsControlResultSerializerInterface $controlResultSerializer = new AcsControlResultSerializer(),
        private readonly JWTTokenExtractorInterface $tokenExtractor = new BearerJWTTokenExtractor(),
    ) {
    }

    public function __invoke(string $alias, string $tenantId, Request $request): Response
    {
        $this->validateScope($request);
        $deliveryExecutionId = $this->deliveryExecutionIdentifierAliasRepository->findDeliveryExecutionId($tenantId, $alias);
        if ($deliveryExecutionId === null) {
            throw new NotFoundHttpException(sprintf('[%s] DeliveryExecution alias not found', $alias));
        }
        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
        } catch (DocumentNotFoundException $e) {
            throw new NotFoundHttpException('Delivery execution not found', $e);
        }

        try {
            $acsControl = $this->controlSerializer->deserialize($request->getContent());
            $acsControlResult = ($this->assessmentControlProcessor)($deliveryExecution, $acsControl);
        } catch (NotControllableDeliveryExecutionException | NotSupportedAssessmentControlAction | LtiExceptionInterface $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $responseBody = $this->controlResultSerializer->serialize($acsControlResult);
        return new Response(
            $responseBody,
            headers: [
                'Content-Type' => AcsServiceInterface::CONTENT_TYPE_CONTROL,
                'Content-Length' => strlen($responseBody),
            ],
        );
    }

    private function validateScope(Request $request): void
    {
        $token = $this->tokenExtractor->extract(
            $this->httpMessageFactory->createRequest($request),
        );

        if ($token->claims()->has('scopes')) {
            $scopes = $token->claims()->get('scopes');
            if (in_array(self::REQUIRED_SCOPE, $scopes)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Required scope not provided');
    }
}
