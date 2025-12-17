<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti;

use App\Lti\Exception\LtiCustomSettingsException;
use App\Service\Lti\LtiTokenResolverInterface;
use Carbon\Carbon;
use Carbon\Exceptions\Exception as CarbonException;
use DateTimeInterface;
use JsonException;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Throwable;

/**
 * All public methods now do not require passing arguments, and will resolve the custom claims via @see LtiTokenResolver
 */
class LtiCustomSettings
{
    public const PARAM_CLOSE_ON = 'deliverySettings.closeOn';
    public const PARAM_DRY_RUN = 'deliverySettings.dryRun';
    public const PARAM_ALL_ITEMS = 'deliverySettings.allItems';
    public const PARAM_ITEM_LAUNCH = 'deliverySettings.item.id';
    public const PARAM_ENABLE_MONITORING = 'proctoringSettings.enableMonitoring';
    public const PARAM_REQUIRE_PROCTOR_AUTHORIZATION = 'proctoringSettings.requireProctorAuthorization';
    public const PARAM_FORCE_PROCTOR_AUTHORIZATION = 'proctoringSettings.forceProctorAuthorization';
    public const PARAM_PROCTORING_CONTEXT_ID = 'proctoringSettings.contextId';
    public const PARAM_PROCTORING_REGISTRATION_ID = 'proctoringSettings.registrationId';
    public const PARAM_PROCTORING_CUSTOM_CLAIMS = 'proctoringSettings.customClaims';
    public const PARAM_FORCE_RESUME = 'deliverySettings.forceResume';
    public const PARAM_NAVIGATION_MODE = 'deliverySettings.navigation';
    public const PARAM_PLUGINS = 'deliverySettings.plugins';
    public const PARAM_ADD_PLUGINS = 'deliverySettings.plugins.add';
    public const PARAM_REMOVE_PLUGINS = 'deliverySettings.plugins.remove';
    public const PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED = 'deliverySettings.plugins.forceFullScreen.enabled';
    public const PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME = 'deliverySettings.plugins.forceFullScreen.autoresume';
    public const PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED = 'deliverySettings.plugins.pauseOnBlur.enabled';
    public const PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME = 'deliverySettings.plugins.pauseOnBlur.autoresume';
    public const PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED = 'deliverySettings.plugins.preventScreenshot.enabled';
    public const PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED = 'deliverySettings.plugins.disableCommands.enabled';
    public const PARAM_READ_ALOUD_OPTION = 'deliverySettings.plugins.readAloud.settings.readAloudOption';
    public const PARAM_READ_ALOUD_OPTION_CONTENT_BASED = 'content-based';
    public const PARAM_READ_ALOUD_OPTION_ENABLED = 'always-enabled';
    public const PARAM_READ_ALOUD_OPTION_DISABLED = 'always-disabled';
    public const PARAM_AUTO_REVIEW_MODE = 'deliverySettings.autoReview.enabled';
    public const PARAM_REVIEW_MODE = 'deliverySettings.review.enabled';
    public const PARAM_REVIEW_MODE_SHOW_CORRECT = 'deliverySettings.review.showCorrect';
    public const PARAM_REVIEW_MODE_SHOW_SCORE = 'deliverySettings.review.showScore';
    public const PARAM_REVIEW_MODE_SHOW_QUESTION = 'deliverySettings.review.showQuestion';
    public const PARAM_REVIEW_MODE_UN_SHUFFLED = 'deliverySettings.review.showUnShuffled';
    public const PARAM_REVIEW_EXTRA_INFO = 'deliverySettings.review.showExtraInfo';
    public const PARAM_REVIEW_DELIVERY_EXECUTION_ID = 'deliverySettings.review.deliveryExecutionId';
    public const PARAM_REVIEW_MODE_ALL_IN_ONE = 'deliverySettings.review.allInOne';
    public const PARAM_ITEM_RUNNER_CONFIG_ELEMENTS = 'deliverySettings.itemRunnerConfigElements';
    public const PARAM_TITLES = 'deliverySettings.titles';
    public const PARAM_TTL = 'deliverySettings.ttl';
    public const PARAM_RESET = 'deliverySettings.reset';
    public const PARAM_STARTS_AT = 'deliverySettings.startsAt';
    public const PARAM_ENDS_AT = 'deliverySettings.endsAt';
    public const PARAM_TEST_TITLE = 'deliverySettings.testTitle';
    public const PARAM_ATTEMPT_ID = 'deliverySettings.attemptId';
    public const PARAM_ATTEMPT_LIMIT = 'deliverySettings.attemptLimit';
    public const PARAM_OUTCOME_SERVICE_CLIENT_ID = 'outcome_service.client_id';
    public const PARAM_DELIVERY_EXECUTION_ALIAS_ID = 'deliveryExecution.alias.id';
    public const PARAM_EXPORT_SETTINGS_ENABLED = 'exportSettings.enabled';
    public const PARAM_EXTRA_TIME = 'extraTime';
    public const PARAM_BATTERY_DELIVERY_ID = 'batterySettings.deliveryId';

    private array $optionsRuntimeCache = [];

    public function __construct(private readonly LtiTokenResolverInterface $ltiTokenResolver)
    {
    }

    public function isDryRunEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_DRY_RUN);
    }

    public function isAllItemsEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_ALL_ITEMS);
    }

    public function hasCustomNavigationMode(array $ltiLaunchParameters = []): bool
    {
        return !$this->isOptionEmpty($ltiLaunchParameters, self::PARAM_NAVIGATION_MODE);
    }

    public function getNavigationMode(array $ltiLaunchParameters = []): ?string
    {
        return $this->getOption($ltiLaunchParameters, self::PARAM_NAVIGATION_MODE);
    }

    public function isItemLaunchEnabled(array $ltiLaunchParameters = []): bool
    {
        return !$this->isOptionEmpty($ltiLaunchParameters, self::PARAM_ITEM_LAUNCH);
    }

    public function getItemLaunch(array $ltiLaunchParameters = []): ?string
    {
        return $this->getOption($ltiLaunchParameters, self::PARAM_ITEM_LAUNCH);
    }

    public function isAutoReviewModeEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_AUTO_REVIEW_MODE);
    }

    public function isReviewModeEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REVIEW_MODE);
    }

    public function isReviewModeWithCorrectAnswer(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REVIEW_MODE_SHOW_CORRECT);
    }

    public function isReviewModeUnShuffleEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REVIEW_MODE_UN_SHUFFLED);
    }

    public function isExportToFileEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_EXPORT_SETTINGS_ENABLED);
    }

    public function getCloseAt(array $ltiLaunchParameters = []): ?DateTimeInterface
    {
        $closeOn = $this->createTimeFromOption($ltiLaunchParameters, self::PARAM_CLOSE_ON);
        $ttl = null;

        if (!$this->isOptionEmpty($ltiLaunchParameters, self::PARAM_TTL)) {
            $ttl = Carbon::now()->addSeconds((int)$this->getOption($ltiLaunchParameters, self::PARAM_TTL));
        }

        // determine the closest time
        if ($ttl && $closeOn) {
            return $ttl->isBefore($closeOn) ? $ttl : $closeOn;
        }

        return $ttl ?? $closeOn ?? null;
    }

    public function getStartsAt(array $ltiLaunchParameters = []): ?Carbon
    {
        return $this->createTimeFromOption($ltiLaunchParameters, self::PARAM_STARTS_AT);
    }

    public function getEndsAt(array $ltiLaunchParameters = []): ?Carbon
    {
        $startsAt = $this->getStartsAt($ltiLaunchParameters);

        if (!$startsAt) {
            return null;
        }

        $endsAt = $this->createTimeFromOption($ltiLaunchParameters, self::PARAM_ENDS_AT);

        return $endsAt?->greaterThan($startsAt) ? $endsAt : null;
    }

    public function getStartRemainingWaitTime(array $ltiLaunchParameters = []): int
    {
        return max(
            (int)Carbon::now()->diffInMilliseconds(
                $this->getStartsAt($ltiLaunchParameters),
                false,
            ),
            0,
        );
    }

    public function getTestTitle(array $ltiLaunchParameters = []): ?string
    {
        return $this->getOption($ltiLaunchParameters, self::PARAM_TEST_TITLE) ?: null;
    }

    public function isReviewModeWithScore(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REVIEW_MODE_SHOW_SCORE);
    }

    public function isShowQuestionEnabledForReview(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REVIEW_MODE_SHOW_QUESTION, true);
    }

    public function isForceResumeModeEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_FORCE_RESUME);
    }

    public function isMonitoringEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_ENABLE_MONITORING);
    }

    public function isProctorAuthorizationRequired(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REQUIRE_PROCTOR_AUTHORIZATION);
    }

    public function isProctorAuthorizationForced(bool $isAvailableForAuthorisation, array $ltiLaunchParameters = []): bool
    {
        return ($this->getAttemptLimit($ltiLaunchParameters) !== null && $isAvailableForAuthorisation)
            || $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_RESET)
            || $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_FORCE_PROCTOR_AUTHORIZATION);
    }

    public function getProctoringContextId(array $ltiLaunchParameters = []): ?string
    {
        return $this->getOption($ltiLaunchParameters, self::PARAM_PROCTORING_CONTEXT_ID);
    }


    public function getProctoringRegistrationId(array $ltiLaunchParameters = []): ?string
    {
        return $this->getOption($ltiLaunchParameters, self::PARAM_PROCTORING_REGISTRATION_ID);
    }

    public function getProctoringCustomClaims(array $ltiLaunchParameters = []): array
    {
        $customClaims = json_decode(
            $this->getOption($ltiLaunchParameters, self::PARAM_PROCTORING_CUSTOM_CLAIMS, ''),
            true,
        );

        return is_array($customClaims) ? $customClaims : [];
    }

    public function getPluginSettings(array $ltiLaunchParameters = []): array
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $pluginSettings = json_decode($this->getOption($ltiLaunchParameters, self::PARAM_PLUGINS, ''), true);

        return is_array($pluginSettings) ? $pluginSettings : [];
    }

    public function getPluginsToAdd(array $ltiLaunchParameters = []): array
    {
        $rawClaimValue = trim($this->getOption($ltiLaunchParameters, self::PARAM_ADD_PLUGINS, ''));
        return $rawClaimValue ? array_map('trim', explode(',', $rawClaimValue)) : [];
    }

    public function getPluginsToRemove(array $ltiLaunchParameters = []): array
    {
        $rawClaimValue = trim($this->getOption($ltiLaunchParameters, self::PARAM_REMOVE_PLUGINS, ''));
        return $rawClaimValue ? array_map('trim', explode(',', $rawClaimValue)) : [];
    }

    public function isReadAloudEnabled(array $ltiLaunchParameters = []): bool
    {
        return in_array($this->getReadAloudOption($ltiLaunchParameters), [
            self::PARAM_READ_ALOUD_OPTION_CONTENT_BASED,
            self::PARAM_READ_ALOUD_OPTION_ENABLED,
        ], true);
    }

    public function isReadAloudForceEnabled(array $ltiLaunchParameters = []): bool
    {
        return in_array($this->getReadAloudOption($ltiLaunchParameters), [
            self::PARAM_READ_ALOUD_OPTION_ENABLED,
        ], true);
    }

    public function isReadAloudConfigured(array $ltiLaunchParameters = []): bool
    {
        return $this->getReadAloudOption($ltiLaunchParameters) !== null;
    }

    public function getReadAloudOption(array $ltiLaunchParameters = []): ?string
    {
        $pluginsSettings = $this->getPluginSettings($ltiLaunchParameters);
        $readAloudOption = $pluginsSettings['readAloud']['readAloudOption'] ?? null;

        return $readAloudOption;
    }

    public function isResetEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_RESET);
    }

    public function isForceFullScreenPresent(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionPresent($ltiLaunchParameters, self::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED);
    }

    public function isForceFullScreenEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED);
    }

    public function isForceFullScreenAutoresumeEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME, true);
    }

    public function isPauseOnBlurPresent(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionPresent($ltiLaunchParameters, self::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED);
    }

    public function isPauseOnBlurEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED);
    }

    public function isPauseOnBlurAutoresumeEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME, true);
    }

    public function isPreventScreenshotEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED);
    }

    public function isPreventScreenshotPresent(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionPresent($ltiLaunchParameters, self::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED);
    }

    public function isDisableCommandsEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED);
    }

    public function isDisableCommandsPresent(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionPresent($ltiLaunchParameters, self::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED);
    }

    public function getCustomTitles(array $ltiLaunchParameters = []): ?array
    {
        $titles = $this->getOption($ltiLaunchParameters, self::PARAM_TITLES);

        return is_string($titles) ? json_decode($titles, true) : null;
    }

    public function getReviewExtraInfoTags(array $ltiLaunchParameters = []): ?array
    {
        $titles = $this->getOption($ltiLaunchParameters, self::PARAM_REVIEW_EXTRA_INFO);

        return is_string($titles) ? json_decode($titles, true) : null;
    }

    public function getReviewDeliveryExecutionId(array $ltiLaunchParameters = []): ?string
    {
        $deliveryExecutionId = $this->getOption($ltiLaunchParameters, self::PARAM_REVIEW_DELIVERY_EXECUTION_ID);

        return $deliveryExecutionId === '' ? null : $deliveryExecutionId;
    }

    public function isReviewModeAllInOneEnabled(array $ltiLaunchParameters = []): bool
    {
        return $this->isOptionEnabled($ltiLaunchParameters, self::PARAM_REVIEW_MODE_ALL_IN_ONE);
    }

    public function getItemRunnerConfigElements(array $ltiLaunchParameters = []): array
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $itemRunnerConfigElements = json_decode($this->getOption($ltiLaunchParameters, self::PARAM_ITEM_RUNNER_CONFIG_ELEMENTS, ''), true);

        return is_array($itemRunnerConfigElements) ? $itemRunnerConfigElements : [];
    }

    public function getAttemptId(array $ltiLaunchParameters = []): ?string
    {
        $attemptId = $this->getOption($ltiLaunchParameters, self::PARAM_ATTEMPT_ID);

        return $attemptId === '' ? null : $attemptId;
    }

    public function getAttemptLimit(array $ltiLaunchParameters = []): ?string
    {
        $attemptLimit = $this->getOption($ltiLaunchParameters, self::PARAM_ATTEMPT_LIMIT);

        return $attemptLimit === '' ? null : $attemptLimit;
    }

    public function getOutcomeServiceClientId(array $ltiLaunchParameters = []): ?string
    {
        $outcomeServiceClientId = $this->getOption($ltiLaunchParameters, self::PARAM_OUTCOME_SERVICE_CLIENT_ID);

        return $outcomeServiceClientId === '' ? null : $outcomeServiceClientId;
    }

    public function getDeliverExecutionIdAlias(array $ltiLaunchParameters = []): ?string
    {
        $outcomeServiceClientId = $this->getOption($ltiLaunchParameters, self::PARAM_DELIVERY_EXECUTION_ALIAS_ID);

        return $outcomeServiceClientId === '' ? null : $outcomeServiceClientId;
    }

    public function getExtraTime(array $ltiLaunchParameters = []): int
    {
        return (int)$this->getOption($ltiLaunchParameters, self::PARAM_EXTRA_TIME, 0);
    }

    public function getBatteryDeliveryId(array $ltiLaunchParameters = []): ?string
    {
        $batteryDeliveryId = $this->getOption($ltiLaunchParameters, self::PARAM_BATTERY_DELIVERY_ID);

        return $batteryDeliveryId === '' ? null : $batteryDeliveryId;
    }

    /**
     * TODO: add validation of other claims?
     *
     * @throws LtiCustomSettingsException
     */
    public function validateClaims(array $ltiLaunchParameters = []): void
    {
        if (!$this->isOptionPresent($ltiLaunchParameters, self::PARAM_TITLES)) {
            return;
        }

        // validate custom test titles
        $titles = $this->getOption($ltiLaunchParameters, self::PARAM_TITLES);
        if (false === is_string($titles)) {
            $this->throwIncorrectFormatException(self::PARAM_TITLES);
        }

        try {
            json_decode($titles, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->throwIncorrectFormatException(self::PARAM_TITLES, $e);
        }
    }

    /**
     * @throws LtiCustomSettingsException
     */
    private function throwIncorrectFormatException(string $claimName, ?Throwable $previous = null): void
    {
        throw new LtiCustomSettingsException(
            sprintf(
                '[%s] Incorrect custom claims format has been provided',
                $claimName,
            ),
            previous: $previous,
        );
    }

    private function isOptionPresent(array $ltiLaunchParameters, string $option): bool
    {
        $customClaims = $this->getCustomClaims($ltiLaunchParameters);

        return isset($customClaims[$option])
            || isset($customClaims[$this->toSnakeCase($option)]);
    }

    private function isOptionEmpty(array $ltiLaunchParameters, string $option): bool
    {
        $customClaims = $this->getCustomClaims($ltiLaunchParameters);

        return empty($customClaims[$option])
            && empty($customClaims[$this->toSnakeCase($option)]);
    }

    private function getOption(array $ltiLaunchParameters, string $option, mixed $default = null): mixed
    {
        $customClaims = $this->getCustomClaims($ltiLaunchParameters);

        return $this->getCachedOptionValue(
            "{$option}_value",
            fn() => $customClaims[$option]
                ?? $customClaims[$this->toSnakeCase($option)]
                ?? $default,
        );
    }

    private function isOptionEnabled(array $ltiLaunchParameters, string $option, mixed $default = null): bool
    {
        $customClaims = $this->getCustomClaims($ltiLaunchParameters);

        return $this->getCachedOptionValue(
            "{$option}_flag",
            fn() => filter_var(
                $customClaims[$option]
                    ?? $customClaims[$this->toSnakeCase($option)]
                    ?? $default,
                FILTER_VALIDATE_BOOLEAN,
            ),
        );
    }

    private function createTimeFromOption(array $ltiLaunchParameters, string $option): ?Carbon
    {
        $customClaims = $this->getCustomClaims($ltiLaunchParameters);

        return $this->getCachedOptionValue(
            "{$option}_time",
            function () use ($customClaims, $option) {
                $rawValue = $customClaims[$option]
                    ?? $customClaims[$this->toSnakeCase($option)]
                    ?? null;
                if (empty($rawValue)) {
                    return null;
                }

                try {
                    return Carbon::parse((string)$rawValue);
                } catch (CarbonException) {
                    return null;
                }
            },
        );
    }

    private function getCustomClaims(array $ltiLaunchParameters = []): array
    {
        $parametersCustomClaims = $ltiLaunchParameters['custom'] ?? [];

        $token = $this->ltiTokenResolver->resolveFromRequest();
        $claims = array_replace(
            [LtiMessagePayloadInterface::CLAIM_LTI_CUSTOM => $parametersCustomClaims],
            null === $token
                ? []
                : $token->claims()->all(),
        );

        return $claims[LtiMessagePayloadInterface::CLAIM_LTI_CUSTOM];
    }

    private function getCachedOptionValue(string $option, callable $valueGetter): mixed
    {
        if (!array_key_exists($option, $this->optionsRuntimeCache)) {
            $this->optionsRuntimeCache[$option] = $valueGetter();
        }

        return $this->optionsRuntimeCache[$option];
    }

    private function toSnakeCase(string $value): string
    {
        return str_replace('.', '_', strtolower($value));
    }
}
