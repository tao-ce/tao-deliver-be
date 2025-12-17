<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Lti\DeepLinking;

use App\Action\Lti\LtiActionTrait;
use App\DynamicQueryApi\Exception\DynamicQueryApiException;
use App\DynamicQueryApi\Gateway\DynamicQueryApiGateway;
use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\Delivery;
use App\Lti\DeepLinking\Builder\ResourceCollectionBuilder;
use App\Validator\Lti\DeepLinking\SubmitDeepLinksActionRequestValidator;
use Exception;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchResponseBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SubmitDeepLinksAction
{
    use LtiActionTrait;
    use LtiDeepLinkingActionTrait;

    public function __construct(
        private readonly RegistrationRepositoryInterface $registrationRepository,
        private readonly SubmitDeepLinksActionRequestValidator $validator,
        private readonly DynamicQueryApiGateway $dynamicQueryApiGateway,
        private readonly DeepLinkingLaunchResponseBuilder $deepLinkingLaunchResponseBuilder,
        private readonly ResourceCollectionBuilder $resourceCollectionBuilder,
        private readonly bool $deepLinkingReturnAutoSubmitForm,
    ) {
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function __invoke(
        Request $request,
        LtiMessagePayloadInterface $ltiMessagePayload,
        string $tenantId,
    ): Response {
        $registration = $this->getLtiRegistrationFromAccessToken($ltiMessagePayload);
        $settings = $ltiMessagePayload->getDeepLinkingSettings();
        $batteryIds = $this->validator->getValidatedRequestParameter($request, SubmitDeepLinksActionRequestValidator::PARAM_BATTERIES, []);
        $batteries = $this->getBatteries(...$batteryIds);

        $deliveryIds = $this->validator->getValidatedRequestParameter($request, SubmitDeepLinksActionRequestValidator::PARAM_DELIVERIES, []);
        $deliveries = $this->getDeliveries(...$deliveryIds);

        if (!$settings) {
            throw new BadRequestHttpException(sprintf(
                '%s claim is required',
                LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_SETTINGS,
            ));
        }

        try {
            $resourceCollection = $this->resourceCollectionBuilder
                ->withBatteries(...$batteries)
                ->withDeliveries(...$deliveries)
                ->getResourceCollection();

            $ltiResponse = $this
                ->deepLinkingLaunchResponseBuilder
                ->buildDeepLinkingLaunchResponse(
                    $resourceCollection,
                    $registration,
                    $settings->getDeepLinkingReturnUrl(),
                    deepLinkingData: $settings->getData(),
                );

            if ($this->deepLinkingReturnAutoSubmitForm) {
                return new JsonResponse(['html' => $ltiResponse->toHtmlRedirectForm()]);
            } else {
                return new JsonResponse(['url' => $ltiResponse->toUrl()]);
            }
        } catch (Exception $exception) {
            return $this->getDeepLinkingErrorResponse(
                $registration,
                $settings,
                $exception,
            );
        }
    }

    /**
     * @throws DynamicQueryApiException
     */
    private function getBatteries(string ...$batteryIds): array
    {
        $searchResponse = $this->dynamicQueryApiGateway->searchBatteriesWithIds(...$batteryIds);

        /** @var Battery[] $batteries */
        $batteries = $searchResponse->getData();

        if ($searchResponse->getTotalResults() !== count($batteryIds)) {
            $batteryIdsFromSearchResponse = array_map(static fn(Battery $battery) => $battery->getId(), $batteries);

            $invalidBatteryIds = array_diff(
                $batteryIds,
                $batteryIdsFromSearchResponse,
            );

            throw new BadRequestHttpException(sprintf(
                'Invalid battery id(s) provided: %s. They may not belong to the given tenant, or they may not exist.',
                implode(', ', $invalidBatteryIds),
            ));
        }

        return $batteries;
    }

    /**
     * @throws DynamicQueryApiException
     */
    private function getDeliveries(string ...$deliveryIds): array
    {
        $searchResponse = $this->dynamicQueryApiGateway->searchDeliveriesWithIds(...$deliveryIds);

        /** @var Delivery[] $deliveries */
        $deliveries = $searchResponse->getData();

        if ($searchResponse->getTotalResults() !== count($deliveryIds)) {
            $deliveryIdsFromSearchResponse = array_map(static fn(Delivery $delivery) => $delivery->getId(), $deliveries);

            $invalidDeliveryIds = array_diff(
                $deliveryIds,
                $deliveryIdsFromSearchResponse,
            );

            throw new BadRequestHttpException(sprintf(
                'Invalid delivery id(s) provided: %s. They may not belong to the given tenant, or they may not exist.',
                implode(', ', $invalidDeliveryIds),
            ));
        }

        return $deliveries;
    }
}
