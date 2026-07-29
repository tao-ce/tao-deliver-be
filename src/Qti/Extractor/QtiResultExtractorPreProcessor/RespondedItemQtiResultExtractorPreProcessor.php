<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor\QtiResultExtractorPreProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Qti\Extractor\ItemResponseStatusResolver;
use App\Environment\FeatureFlagAdapterInterface;
use qtism\common\datatypes\QtiBoolean;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\RouteItem;

readonly class RespondedItemQtiResultExtractorPreProcessor implements QtiResultExtractorPreProcessorInterface
{
    private const FEATURE_FLAG = 'ADD_CUSTOM_RESPONDED_OUTCOME_VARIABLE_TO_RESULT';
    private const OUTCOME_VARIABLE = 'RESPONDED';

    public function __construct(
        private FeatureFlagAdapterInterface $featureFlagAdapter,
        private ItemResponseStatusResolver $itemResponseStatusResolver,
    ) {
    }

    public function process(DeliveryExecution $deliveryExecution, AssessmentTestSession $testSession): void
    {
        if (!$this->featureFlagAdapter->isEnabled($deliveryExecution->getTenantId(), self::FEATURE_FLAG)) {
            return;
        }

        $routeItems = $testSession->getRoute()->getAllRouteItems();

        /** @var RouteItem $routeItem */
        foreach ($routeItems as $routeItem) {
            $itemSession = $testSession->getAssessmentItemSessionStore()->getAssessmentItemSession(
                $routeItem->getAssessmentItemRef(),
                $routeItem->getOccurence(),
            );
            $itemSession->setVariable(
                new OutcomeVariable(
                    self::OUTCOME_VARIABLE,
                    Cardinality::SINGLE,
                    BaseType::BOOLEAN,
                    new QtiBoolean(
                        $this->itemResponseStatusResolver->isRespondedTo($itemSession, $deliveryExecution),
                    ),
                ),
            );
        }
    }
}
