<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Stubs;

use OAT\Library\EnvironmentManagementClient\Model\FeatureFlag;
use OAT\Library\EnvironmentManagementClient\Model\FeatureFlagCollection;
use OAT\Library\EnvironmentManagementClient\Repository\FeatureFlagRepositoryInterface;

class FeatureFlagRepositoryStub implements FeatureFlagRepositoryInterface
{
    private const SCORING_SUBMISSION_ENABLED = 'SCORING_SUBMISSION_ENABLED';
    private const DATA_STORE_ENABLE_RESULTS_TRANSFER = 'DATA_STORE_ENABLE_RESULTS_TRANSFER';
    private const SCORING_OWNS_GRADING_PROGRESS = 'SCORING_OWNS_GRADING_PROGRESS';
    private const TESTRUNNER_READALOUD_FORCED = 'TESTRUNNER_READALOUD_FORCED';
    private const FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED = 'FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED';
    private const ITEM_CONTENT_UPLOAD_ENABLED = 'ITEM_CONTENT_UPLOAD_ENABLED';
    private const COMPILE_RESPONSE_MAPPINGS_ENABLED = 'COMPILE_RESPONSE_MAPPINGS_ENABLED';

    public function __construct()
    {
    }

    public function find(string $tenantId, string $featureFlagId): FeatureFlag
    {
        if (self::SCORING_SUBMISSION_ENABLED == $featureFlagId) {
            return new FeatureFlag(self::SCORING_SUBMISSION_ENABLED, "true");
        }
        if (self::DATA_STORE_ENABLE_RESULTS_TRANSFER == $featureFlagId) {
            return new FeatureFlag(self::DATA_STORE_ENABLE_RESULTS_TRANSFER, "true");
        }
        if (self::SCORING_OWNS_GRADING_PROGRESS == $featureFlagId && $tenantId == "tenantId1") {
            return new FeatureFlag(self::SCORING_OWNS_GRADING_PROGRESS, "false");
        }
        if (self::SCORING_OWNS_GRADING_PROGRESS == $featureFlagId) {
            return new FeatureFlag(self::SCORING_OWNS_GRADING_PROGRESS, "true");
        }
        if (self::TESTRUNNER_READALOUD_FORCED === $featureFlagId && $tenantId == "7") {
            return new FeatureFlag(self::TESTRUNNER_READALOUD_FORCED, "true");
        }
        if (self::FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED === $featureFlagId && $tenantId == "8") {
            return new FeatureFlag(self::FEATURE_FLAG_TEST_NAVIGATION_NONLINEAR_RESTRICTED, "true");
        }
        if (self::ITEM_CONTENT_UPLOAD_ENABLED === $featureFlagId) {
            return new FeatureFlag(self::ITEM_CONTENT_UPLOAD_ENABLED, "false");
        }
        if (self::COMPILE_RESPONSE_MAPPINGS_ENABLED === $featureFlagId) {
            return new FeatureFlag(self::COMPILE_RESPONSE_MAPPINGS_ENABLED, "false");
        }

        return new FeatureFlag("feature", "true");
    }

    public function findAll(string $tenantId): FeatureFlagCollection
    {
        $collection = new FeatureFlagCollection();
        $collection->add(new FeatureFlag(self::SCORING_SUBMISSION_ENABLED, "true"));
        $collection->add(new FeatureFlag(self::DATA_STORE_ENABLE_RESULTS_TRANSFER, "true"));
        $collection->add(new FeatureFlag(self::SCORING_OWNS_GRADING_PROGRESS, "true"));
        $collection->add(new FeatureFlag(self::ITEM_CONTENT_UPLOAD_ENABLED, "false"));
        $collection->add(new FeatureFlag(self::COMPILE_RESPONSE_MAPPINGS_ENABLED, "false"));

        return $collection;
    }
}
