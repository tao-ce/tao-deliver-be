<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\tests\AssessmentItemSession;
use Error;

class QtiVariableExtractor
{
    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
    ) {
    }

    public function extractTestOutcomeVariables(DeliveryExecution $deliveryExecution): iterable
    {
        $testSession  = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        foreach ($testSession->getAllVariables()->getArrayCopy() as $qtiVariable) {
            if (!$qtiVariable instanceof OutcomeVariable) {
                continue;
            }

            try {
                $value = $qtiVariable->getValue()?->getValue();
            } catch (\Error) {
                $value = (string)$qtiVariable->getValue();
            }
            yield [
                'id' => $qtiVariable->getIdentifier(),
                'cardinality' => Cardinality::getNameByConstant($qtiVariable->getCardinality()) ?: null,
                'baseType' => BaseType::getNameByConstant($qtiVariable->getBaseType()) ?: null,
                'value' => $value,
            ];
        }
    }
}
