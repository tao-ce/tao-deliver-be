<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor\QtiResultExtractorPreProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use qtism\runtime\tests\AssessmentTestSession;

/**
 * A preprocessor to modify the TestSession before AssessmentResult is generated.
 */
interface QtiResultExtractorPreProcessorInterface
{
    public function process(DeliveryExecution $deliveryExecution, AssessmentTestSession $testSession): void;
}
