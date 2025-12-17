<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\DeepLinking\Builder;

use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\Delivery;
use App\Service\ApplicationInfoService;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Core\Resource\ResourceCollection;
use Symfony\Component\Routing\RouterInterface;

class ResourceCollectionBuilder
{
    private ResourceCollection $resourceCollection;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly ApplicationInfoService $applicationInfoService,
    ) {
        $this->reset();
    }

    public function reset(): self
    {
        $this->resourceCollection = new ResourceCollection();

        return $this;
    }

    public function getResourceCollection(): ResourceCollection
    {
        $resourceCollection = $this->resourceCollection;
        $this->reset();

        return $resourceCollection;
    }

    public function withDeliveries(Delivery ...$deliveries): self
    {
        foreach ($deliveries as $delivery) {
            $ltiResourceLink = new LtiResourceLink(
                $delivery->getId(),
                [
                    'title' => $delivery->getConfiguration()['label'] ?? 'undefined',
                    'url' => sprintf(
                        '%s%s',
                        $this->applicationInfoService->getBackendUrl(),
                        $this->router->generate('api_v1_launch_lti_1p3', ['deliveryId' => $delivery->getId()]),
                    ),
                ],
            );

            $this->resourceCollection->add($ltiResourceLink);
        }

        return $this;
    }

    public function withBatteries(Battery ...$batteries): self
    {
        foreach ($batteries as $battery) {
            $ltiResourceLink = new LtiResourceLink(
                $battery->getId(),
                [
                    'title' => $battery->getName(),
                    'url' => sprintf(
                        '%s%s',
                        $this->applicationInfoService->getBackendUrl(),
                        $this->router->generate('api_v1_launch_lti_1p3_battery', ['batteryId' => $battery->getId()]),
                    ),
                ],
            );

            $this->resourceCollection->add($ltiResourceLink);
        }

        return $this;
    }
}
