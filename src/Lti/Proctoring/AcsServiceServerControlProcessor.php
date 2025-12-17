<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Proctoring;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\LtiCustomSettings;
use App\Service\AssessmentControl\AssessmentControlProcessor;
use App\Service\AssessmentControl\Exception\NotControllableDeliveryExecutionException;
use App\Service\AssessmentControl\Exception\NotSupportedAssessmentControlAction;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\Lti\LtiProctoringService;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\Lti1p3Proctoring\Service\Server\Processor\AcsServiceServerControlProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class AcsServiceServerControlProcessor implements AcsServiceServerControlProcessorInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
        private LtiCustomSettings $customSettings,
        private AssessmentControlProcessor $assessmentControlProcessor,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public function process(
        ?RegistrationInterface $registration,
        AcsControlInterface $control,
    ): AcsControlResultInterface {
        if (!in_array($control->getAction(), AcsControlInterface::SUPPORTED_ACTIONS)) {
            throw new BadRequestHttpException(
                sprintf('Invalid ACS action provided: %s', $control->getAction()),
            );
        }

        $request = $this->requestStack->getCurrentRequest();
        $routeParameters = $request->attributes->get('_route_params');

        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail(
                $routeParameters['deliveryExecutionId'] ?? null,
            );
        } catch (DocumentNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $this->validateDeliveryExecutionParameters($deliveryExecution, $control);

        try {
            return ($this->assessmentControlProcessor)($deliveryExecution, $control);
        } catch (NotSupportedAssessmentControlAction | NotControllableDeliveryExecutionException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }

    private function validateDeliveryExecutionParameters(
        DeliveryExecution $deliveryExecution,
        AcsControlInterface $control,
    ): void {
        $ltiParameters = $deliveryExecution->getLtiLaunchParameters();

        if (
            !$this->customSettings->isMonitoringEnabled($ltiParameters)
            || !$this->featureFlagAdapter->isEnabled(
                $deliveryExecution->getTenantId(),
                LtiProctoringService::FEATURE_FLAG,
                true,
            )
        ) {
            throw new BadRequestHttpException(
                sprintf('Delivery execution "%s" can not be controlled by ACS', $deliveryExecution->getId()),
            );
        }

        if (
            empty($ltiParameters['resource_link_id'])
            || $control->getResourceLink()->getIdentifier() !== $ltiParameters['resource_link_id']
        ) {
            throw new AccessDeniedHttpException('Incorrect resource link id has been provided');
        }
    }
}
