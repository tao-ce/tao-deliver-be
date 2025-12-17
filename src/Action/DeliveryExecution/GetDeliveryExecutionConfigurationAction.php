<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Builder\DeliveryExecution\DeliveryExecutionConfigurationBuilder;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Tenant\Model\EmptyTestRunnerTheme;
use App\Domain\Tenant\Model\TestRunnerSettings;
use App\Domain\Tenant\Model\TestRunnerSettingsRepositoryInterface;
use App\Environment\FeatureFlagAdapterInterface;
use App\Generator\UrlGenerator;
use App\Lti\LtiCustomSettings;
use App\Responder\SerializerResponder;
use App\Response\GetDeliveryExecutionConfigurationResponse;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\Battery\BatteryService;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionCreatorInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Lti\LtiLaunchService;
use App\Service\Lti\LtiTokenResolverInterface;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\RealTimeService;
use OAT\Library\TenantManagement\Model\TestRunnerTheme;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class GetDeliveryExecutionConfigurationAction implements DeliveryExecutionSessionController
{
    private const FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED = 'FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED';

    public function __construct(
        private DeliveryExecutionCreatorInterface $deliveryExecutionCreator,
        private LtiLaunchService $launchService,
        private TestRunnerSettingsRepositoryInterface $tenantPreferencesRepository,
        private SerializerResponder $responder,
        private LtiCustomSettings $ltiCustomSettings,
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private LoggerInterface $auditDeliveryExecutionLogger,
        private RealTimeService $realTimeService,
        private UrlGenerator $urlGenerator,
        private BatteryService $batteryService,
        private BatteryNavigationService $batteryNavigationService,
        private LtiTokenResolverInterface $ltiTokenResolver,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public function __invoke(DeliveryExecution $deliveryExecution): JsonResponse
    {
        $testRunnerSettings = $this->tenantPreferencesRepository->findTestRunnerSettings($deliveryExecution);
        $delivery = $testRunnerSettings->getDelivery();

        if ($deliveryExecution->isDeleted()) {
            $deliveryExecution = $this->deliveryExecutionCreator->createDeliveryExecutionFromSeed(
                $delivery,
                $deliveryExecution,
                $deliveryExecution->getLtiLaunchParameters(),
            );
            $this->launchService->launchTest(
                $deliveryExecution,
                $deliveryExecution->getLtiLaunchParameters(),
                $delivery,
            );
        }

        $configuration = $testRunnerSettings->getConfiguration();
        $configuration = $this->overrideReviewProviders($deliveryExecution, $configuration);
        $configuration = $this->overrideExportProviders($deliveryExecution, $configuration);
        $configuration = $this->overrideTitles($deliveryExecution, $configuration);
        $configuration = $this->setOptions($deliveryExecution, $configuration);
        $configuration = $this->setRealTimeServiceConfiguration($configuration);
        $configuration = $this->configurePlugins($deliveryExecution, $configuration);
        $configuration = $this->filterPlugins($deliveryExecution, $configuration);
        $configuration = $this->setPasswordProtection($deliveryExecution, $configuration);
        $configuration = $this->setBatteryContext($deliveryExecution, $configuration);
        $configuration = $this->setLocaleDetails($deliveryExecution, $delivery, $configuration);

        $theme = $this->getTestRunnerTheme($testRunnerSettings, $configuration);

        $response = new GetDeliveryExecutionConfigurationResponse(
            $testRunnerSettings->getDelivery(),
            $deliveryExecution,
            $theme,
            !empty($configuration) ? $configuration : null,
        );

        return $this->responder->createJsonResponse($response);
    }

    private function configurePlugins(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        $builder = new DeliveryExecutionConfigurationBuilder($configuration);

        $this->configureAssessmentPlugins($deliveryExecution, $builder);
        $this->configureCommonPlugins($deliveryExecution, $builder);

        return $builder->build();
    }

    private function configureAssessmentPlugins(
        DeliveryExecution $deliveryExecution,
        DeliveryExecutionConfigurationBuilder $builder,
    ): void {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        if ($deliveryExecution->isReview()) {
            return;
        }

        if ($this->hasItemWithConfigurableSettings($deliveryExecution)) {
            $builder->withSettingsPlugin();
        }

        if ($this->ltiCustomSettings->isForceFullScreenEnabled($ltiLaunchParameters)) {
            $builder
                ->withForceFullScreenPlugin(
                    $this->ltiCustomSettings->isForceFullScreenAutoresumeEnabled($ltiLaunchParameters),
                )
                ->withoutFullScreenPlugin();
        } elseif ($this->ltiCustomSettings->isForceFullScreenPresent($ltiLaunchParameters)) {
            $builder->withoutForceFullScreenPlugin();
        }

        if ($this->ltiCustomSettings->isPauseOnBlurEnabled($ltiLaunchParameters)) {
            $builder->withPauseOnBlurPlugin(
                $this->ltiCustomSettings->isPauseOnBlurAutoresumeEnabled($ltiLaunchParameters),
            );
        } elseif ($this->ltiCustomSettings->isPauseOnBlurPresent($ltiLaunchParameters)) {
            $builder->withoutPauseOnBlurPlugin();
        }

        $builder->overridePluginOptions(
            $this->ltiCustomSettings->getPluginSettings($ltiLaunchParameters),
        );

        foreach ($this->ltiCustomSettings->getPluginsToAdd($ltiLaunchParameters) as $module) {
            $builder->addPlugin($module);
        }
    }

    private function configureCommonPlugins(
        DeliveryExecution $deliveryExecution,
        DeliveryExecutionConfigurationBuilder $builder,
    ): void {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        if ($this->ltiCustomSettings->isReadAloudConfigured($ltiLaunchParameters)) {
            if ($this->isDisableReadAloudPlugin($deliveryExecution)) {
                $builder->withoutReadAloudPlugin();
            } else {
                $builder->withReadAloudPlugin();
            }
        }

        if ($this->ltiCustomSettings->isPreventScreenshotEnabled($ltiLaunchParameters)) {
            $builder->withPreventScreenshotPlugin();
        } elseif ($this->ltiCustomSettings->isPreventScreenshotPresent($ltiLaunchParameters)) {
            $builder->withoutPreventScreenshotPlugin();
        }

        if ($this->ltiCustomSettings->isDisableCommandsEnabled($ltiLaunchParameters)) {
            $builder->withDisableCommandsPlugin();
            $builder->withDisableRightClickPlugin();
        } elseif ($this->ltiCustomSettings->isDisableCommandsPresent($ltiLaunchParameters)) {
            $builder->withoutDisableCommandsPlugin();
            $builder->withoutDisableRightClickPlugin();
        }

        foreach ($this->ltiCustomSettings->getPluginsToRemove($ltiLaunchParameters) as $module) {
            $builder->removePlugin($module);
        }
    }

    private function isDisableReadAloudPlugin(DeliveryExecution $deliveryExecution): bool
    {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        return !$this->ltiCustomSettings->isReadAloudEnabled($ltiLaunchParameters)
            || (
                LtiCustomSettings::PARAM_READ_ALOUD_OPTION_CONTENT_BASED === $this->ltiCustomSettings->getReadAloudOption(
                    $ltiLaunchParameters,
                )
                && !$this->hasItemWithTextToSpeechEnabled($deliveryExecution)
            );
    }

    private function setOptions(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        if ($deliveryExecution->isReview()) {
            $configuration = $this->setReviewOptions($deliveryExecution, $configuration);
        }

        if ($this->ltiCustomSettings->hasCustomNavigationMode($ltiLaunchParameters)) {
            $configuration['options'][LtiCustomSettings::PARAM_NAVIGATION_MODE] = $this->ltiCustomSettings->getNavigationMode(
                $ltiLaunchParameters,
            );
        }

        if (
            $this->featureFlagAdapter->isEnabled(
                $deliveryExecution->getTenantId(),
                self::FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED,
            )
        ) {
            $configuration['options']['nonLinearRestricted'] = true;
        }

        $configuration['options']['waitTimeRemaining'] =
            $this->ltiCustomSettings->getStartRemainingWaitTime($ltiLaunchParameters);
        $configuration['options']['startsAt'] =
            $this->ltiCustomSettings->getStartsAt($ltiLaunchParameters)?->format(DATE_ATOM);
        $configuration['options']['endsAt'] =
            $this->ltiCustomSettings->getEndsAt($ltiLaunchParameters)?->format(DATE_ATOM);

        $configuration['options']['testTitle'] = $this->ltiCustomSettings->getTestTitle($ltiLaunchParameters)
            ?? $this->deliveryExecutionPropertyService->getQtiTestTitle($deliveryExecution);

        if (
            $this->ltiTokenResolver->hasOneOfRoles([
                LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR,
            ])
        ) {
            $configuration['options']['plugins']['inlineComments']['mode'] = ['read', 'write'];
        }

        $configuration['options']['itemRunnerConfig']['elements'] = array_replace_recursive(
            $configuration['options']['itemRunnerConfig']['elements'] ?? [],
            $this->ltiCustomSettings->getItemRunnerConfigElements($ltiLaunchParameters),
        );

        return $configuration;
    }

    private function filterPlugins(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        if (
            $this->ltiCustomSettings->hasCustomNavigationMode($ltiLaunchParameters)
            && $this->ltiCustomSettings->getNavigationMode($ltiLaunchParameters) === 'none'
        ) {
            $configuration = $this->removePlugin('navigatorPlugin', $configuration);
        }

        if ($this->ltiCustomSettings->isReviewModeAllInOneEnabled($ltiLaunchParameters)) {
            $configuration = $this->removePlugin('titlePlugin', $configuration);
            $configuration = $this->removePlugin('navigatorPlugin', $configuration);
        }

        return $configuration;
    }

    private function setReviewOptions(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        $options = [
            'showQuestion' => $this->ltiCustomSettings->isShowQuestionEnabledForReview($ltiLaunchParameters),
            'showCorrect' => $this->ltiCustomSettings->isReviewModeWithCorrectAnswer($ltiLaunchParameters),
            'showScore' => $this->ltiCustomSettings->isReviewModeWithScore($ltiLaunchParameters),
            'itemLaunch' => $this->ltiCustomSettings->isItemLaunchEnabled($ltiLaunchParameters),
            'showUnShuffled' => $this->ltiCustomSettings->isReviewModeUnShuffleEnabled($ltiLaunchParameters),
            'allInOne' => $this->ltiCustomSettings->isReviewModeAllInOneEnabled($ltiLaunchParameters),
        ];

        if ($options['allInOne']) {
            $options['showQuestion'] = false;
            $options['showCorrect'] = false;
            $options['showScore'] = false;
        }

        $configuration['options'] = array_merge($configuration['options'] ?? [], ['review' => $options]);

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - review options have been set in configuration',
                $deliveryExecution->getId(),
            ),
        );

        return $configuration;
    }

    private function getTestRunnerTheme(
        TestRunnerSettings $testRunnerSettings,
        array $configuration,
    ): TestRunnerTheme|EmptyTestRunnerTheme {
        $theme = $testRunnerSettings->getTheme();

        // no menuPanel plugin loaded => also hide its button
        if (!in_array(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_MENU_PANEL,
            $configuration['providers']['plugins'],
        )) {
            $testRunnerTheme = $theme->getTestRunner();
            $testRunnerTheme['hideMenuButton'] = true;

            return new TestRunnerTheme(
                $theme->getPlatform(),
                $testRunnerTheme,
                $theme->getItemRunner(),
                $theme->getDefault(),
            );
        }

        return $theme;
    }

    private function overrideTitles(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        $customTitles = $this->ltiCustomSettings->getCustomTitles($deliveryExecution->getLtiLaunchParameters());

        if (null !== $customTitles) {
            $configuration['options'] = array_merge($configuration['options'] ?? [], ['titles' => $customTitles]);
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - custom titles have been applied',
                    $deliveryExecution->getId(),
                ),
            );
        }

        return $configuration;
    }

    private function overrideReviewProviders(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        if (isset($configuration['reviewProviders'])) {
            if ($deliveryExecution->isReview()) {
                $this->auditDeliveryExecutionLogger->info(
                    sprintf(
                        '[%s] - providers have been replaced by review providers',
                        $deliveryExecution->getId(),
                    ),
                );
                $configuration['providers'] = $configuration['reviewProviders'];
            }

            unset($configuration['reviewProviders']);
        }

        return $configuration;
    }

    private function overrideExportProviders(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        if (isset($configuration['exportProviders'])) {
            if ($this->ltiCustomSettings->isExportToFileEnabled()) {
                $this->auditDeliveryExecutionLogger->info(
                    sprintf(
                        '[%s] - providers have been replaced by review providers',
                        $deliveryExecution->getId(),
                    ),
                );
                $configuration['providers'] = $configuration['exportProviders'];
            }

            unset($configuration['exportProviders']);
        }

        return $configuration;
    }

    private function removePlugin(string $pluginId, ?array $configuration): ?array
    {
        if (empty($configuration['providers']['plugins'])) {
            return $configuration;
        }

        $configuration['providers']['plugins'] = array_values(
            array_filter(
                $configuration['providers']['plugins'],
                static function ($plugin) use ($pluginId) {
                    return $plugin['id'] !== $pluginId;
                },
            ),
        );

        return $configuration;
    }

    private function hasItemWithTextToSpeechEnabled(DeliveryExecution $deliveryExecution): bool
    {
        return in_array(
            'x-tao-option-tts',
            $this->deliveryExecutionPropertyService->getAllItemCategories($deliveryExecution),
            true,
        );
    }

    private function hasItemWithConfigurableSettings(DeliveryExecution $deliveryExecution): bool
    {
        return (bool)array_intersect(
            ['x-tao-option-eliminator', 'x-tao-option-answerMasking'],
            $this->deliveryExecutionPropertyService->getAllItemCategories($deliveryExecution),
        );
    }

    private function setRealTimeServiceConfiguration(array $configuration): array
    {
        $configuration['options']['realTimeService'] = $configuration['options']['realTimeService'] ?? [];
        $configuration['options']['realTimeService'] += $this->realTimeService->getConfiguration();

        return $configuration;
    }

    private function setPasswordProtection(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        if ($deliveryExecution->isReview()) {
            return $configuration;
        }

        $batteryId = $deliveryExecution->getLtiLaunchParameters()['battery_id'] ?? null;

        if (empty($batteryId)) {
            return $configuration;
        }

        try {
            $battery = $this->batteryService->findBatteryOrFail($batteryId);
        } catch (NotFoundHttpException) {
            $this->auditDeliveryExecutionLogger->error(
                sprintf(
                    '[%s] - password protection options were not set because battery with ID %s was not found',
                    $deliveryExecution->getId(),
                    $batteryId,
                ),
            );

            return $configuration;
        }

        $batteryDelivery = $this->batteryService->getAssignedDelivery(
            $battery,
            $deliveryExecution->getLtiLaunchParameters(),
        );

        $validationEndpoint = $this->urlGenerator->generate(
            'api_v1_battery_password_validation',
            ['id' => $deliveryExecution->getId()],
        );

        $configuration['options']['passwordProtection'] = [
            'delivery' => [
                'isProtected' => $batteryDelivery->isPasswordProtected() ?? false,
                'id' => $batteryDelivery->id,
            ],
            'validationEndpoint' => $validationEndpoint,
        ];

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - password protection options have been set in configuration',
                $deliveryExecution->getId(),
            ),
        );

        return $configuration;
    }

    private function setBatteryContext(DeliveryExecution $deliveryExecution, array $configuration): array
    {
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        // When reviewing a specific delivery execution, we don't want to have the next-delivery URL as the battery context
        if (
            $deliveryExecution->isReview()
            && $this->ltiCustomSettings->getReviewDeliveryExecutionId($ltiLaunchParameters)
        ) {
            return $configuration;
        }
        $batteryContext = $this->batteryNavigationService->getBatteryContext($deliveryExecution);

        if ($batteryContext !== null) {
            $configuration['batteryContext'] = $batteryContext;
        }

        return $configuration;
    }

    private function setLocaleDetails(
        DeliveryExecution $deliveryExecution,
        Delivery $delivery,
        array $configuration,
    ): array {
        $locale = $deliveryExecution->getLocale()
            ?? ($deliveryExecution->isStateInitial() ? null : $delivery->getMainLocale());

        $configuration['options']['localization']['locale'] = $locale;
        $configuration['options']['localization']['mainLocale'] = $delivery->getMainLocale();
        $launchParameters = $deliveryExecution->getLtiLaunchParameters();

        if (!empty($launchParameters['battery_id'])) {
            $battery = $this->batteryService->findBatteryOrFail($launchParameters['battery_id']);
            $configuration['options']['localization']['supportedLocales'] = $this->batteryService->getCommonLocales(
                $battery,
            );
            $configuration['options']['localization']['isMultiLanguage'] = $this->batteryService->isMultiLanguageBattery(
                $battery,
            );
        } else {
            $configuration['options']['localization']['supportedLocales'] = $delivery->getSupportedLocales();
            $configuration['options']['localization']['isMultiLanguage'] = $delivery->isMultiLanguage();
        }

        return $configuration;
    }
}
