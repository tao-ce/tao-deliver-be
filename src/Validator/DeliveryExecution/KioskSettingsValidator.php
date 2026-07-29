<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Validator\AbstractDeliveryExecutionAwareRequestValidator;
use App\Validator\Exception\RequestValidationException;
use InvalidArgumentException;
use OAT\Library\EnvironmentManagementClient\Exception\ConfigurationNotFoundException;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class KioskSettingsValidator extends AbstractDeliveryExecutionAwareRequestValidator
{
    public function __construct(
        ValidatorInterface $validator,
        private readonly ConfigurationRepositoryInterface $configurationRepository,
    ) {
        parent::__construct($validator);
    }

    public function getValidatedRequestParameters(?Request $request = null): array
    {
        try {
            $settings = parent::getValidatedRequestParameters($request ?? new Request());
        } catch (RequestValidationException) {
            $settings = [];
        }

        $settings['enabled'] ??= false;
        if ($settings['enabled']) {
            $settings['minVersion'] = $settings['minimumVersion'] ?? '0.0.0';
        }
        unset($settings['minimumVersion']);
        if (empty($settings['processDenyList'])) {
            unset($settings['processDenyList']);
        }

        return $settings;
    }

    protected function getRequestData(Request $request): array
    {
        try {
            return (array)$this->configurationRepository->find(
                $this->getDeliveryExecution()->getTenantId(),
                'portal.secure_browser_settings',
            )?->getArrayValue();
        } catch (ConfigurationNotFoundException|InvalidArgumentException) {
            return [];
        }
    }

    protected function getRequestValidationConstraint(): Collection
    {
        return new Collection(
            [
                'enabled' => new Optional([
                    new Type('bool'),
                ]),
                'downloads' => new Optional([
                    new All(
                        new Collection(
                            [
                                'url' => new Required([new Type('string')]),
                            ],
                            allowExtraFields: true,
                        ),
                    ),
                ]),
                'minimumVersion' => new Optional([new Type('string')]),
                'redirectUrl' => new Optional([new Type('string')]),
                'installationInstructions' => new Optional([new Type('string')]),
                'processDenyList' => new Optional(new All(new Collection([
                    'name' => [new NotBlank(), new Type('string')],
                    'label' => [new NotBlank(), new Type('string')],
                ]))),
            ],
            allowExtraFields: true,
        );
    }
}
