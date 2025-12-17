<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Lti\Proctoring;

use App\Repository\DeliveryRepository;
use App\Responder\SerializerResponder;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\Lti\LtiLaunchService;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\RegisteredClaims;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayload;
use OAT\Library\Lti1p3Core\Security\Jwt\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StartAssessmentAction
{
    public function __construct(
        private RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private DeliveryRepository $deliveryRepository,
        private SerializerResponder $responder,
        private LtiLaunchService $launchService,
        private LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function __invoke(Request $request, string $deliveryExecutionId): Response
    {
        $this->auditDeliveryExecutionLogger->info(
            sprintf('[%s] - start assessment request', $deliveryExecutionId),
        );

        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
        } catch (DocumentNotFoundException $e) {
            return $this->responder->createErrorJsonResponse($e, Response::HTTP_NOT_FOUND);
        }

        $parameters = $deliveryExecution->getLtiLaunchParameters();
        $startAssessmentMessage = new LtiMessagePayload(new Token(
            (new Parser(new JoseEncoder()))->parse($request->request->get('JWT')),
        ));
        $parameters['proctoring_end_assessment_return'] = $startAssessmentMessage->getProctoringEndAssessmentReturn();
        $parameters['assessment_platform_client_id'] = $startAssessmentMessage->getMandatoryClaim(RegisteredClaims::ISSUER);
        $parameters['assessment_platform_issuer'] = $startAssessmentMessage->getMandatoryClaim(RegisteredClaims::AUDIENCE);
        if (empty($parameters['assessment_platform_issuer'])) {
            return $this->responder->createJsonResponse(
                ['error' => ['message' => 'Audience claim expected']],
                Response::HTTP_BAD_REQUEST,
            );
        }
        $parameters['assessment_platform_issuer'] = reset($parameters['assessment_platform_issuer']);
        $deliveryExecution->setLtiLaunchParameters($parameters);
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        return $this->launchService->launchTest(
            $deliveryExecution,
            $parameters,
            $this->deliveryRepository->find($deliveryExecution->getDeliveryId()),
        );
    }
}
