<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Domain\Tenant\Model\EmptyTestRunnerTheme;
use App\Generator\UrlGenerator;
use App\Lti\LtiCustomSettings;
use App\Response\GetDeliveryExecutionConfigurationResponse;
use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Service\Locale\Dto\UserLocaleProviderContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class GetDeliveryExecutionConfigurationNormalizer implements NormalizerInterface
{
    public function __construct(
        private UserLocaleProviderInterface $userLocaleProvider,
        private UrlGenerator $urlGenerator,
        private LtiCustomSettings $ltiCustomSettings,
    ) {
    }

    /**
     * @param GetDeliveryExecutionConfigurationResponse $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $deliveryExecution = $object->getDeliveryExecution();
        $testRunnerTheme = $object->getTestRunnerTheme();
        $ltiParameters = $deliveryExecution->getLtiLaunchParameters();
        $testRunnerConfiguration = $object->getTestRunnerConfiguration() ?? [];

        $isAnonymousScoring = $this->ltiCustomSettings->isAnonymousScoring();
        $testTakerName = $isAnonymousScoring && $deliveryExecution->isReview()
            ? ($this->ltiCustomSettings->getTestTakerName() ?? $ltiParameters['user_name'])
            : $ltiParameters['user_name'];

        $updatedConfiguration = [
            'hasItemState' => $deliveryExecution->getExtraStateData()->hasItemStates(),
            'deliveryId' => $deliveryExecution->getDeliveryId(),
            'deliveryExecutionId' => $deliveryExecution->getId(),
            'locale' => $object->getTranslatedTestLocale() ?? $this->provideUserLocale($object),
            'testTaker' => [
                'id' => $deliveryExecution->getUserId(),
                'name' => $testTakerName,
                'firstName' => $isAnonymousScoring ? null : ($ltiParameters['given_name'] ?? null),
                'lastName' => $isAnonymousScoring ? null : ($ltiParameters['family_name'] ?? null),
            ],
            'options' => [
                'endAssessmentUrl' => empty($ltiParameters['proctoring_end_assessment_return'])
                    ? null
                    : $this->urlGenerator->generate(
                        'api_v1_proctoring_end_assessment_return',
                        [
                            'deliveryExecutionId' => $deliveryExecution->getId(),
                        ],
                        UrlGeneratorInterface::NETWORK_PATH,
                    ),
                'exitUrl' => $ltiParameters['launch_presentation_return_url'] ?? null,
            ],
            'themes' => $testRunnerTheme instanceof EmptyTestRunnerTheme
                ? null
                : [
                    'platform' => $testRunnerTheme->getPlatform(),
                    'testRunner' => $testRunnerTheme->getTestRunner(),
                    'itemRunner' => $testRunnerTheme->getItemRunner(),
                    'default' => $testRunnerTheme->getDefault(),
                ],
        ];

        if (isset($testRunnerConfiguration['options']['locale']) && $object->getTranslatedTestLocale()) {
            $updatedConfiguration['options']['locale'] = $object->getTranslatedTestLocale();
        }

        return [
            'data' => array_replace_recursive(
                $testRunnerConfiguration,
                $updatedConfiguration,
            ),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof GetDeliveryExecutionConfigurationResponse;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            GetDeliveryExecutionConfigurationResponse::class => true,
        ];
    }

    private function provideUserLocale($object): string
    {
        $userLocalProviderContext = new UserLocaleProviderContext(
            $object->getDeliveryExecution(),
            $object->getDelivery(),
            $object->getTestRunnerConfiguration(),
        );

        return $this->userLocaleProvider->provide($userLocalProviderContext);
    }
}
