<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Validator\AbstractDeliveryExecutionAwareRequestValidator;
use App\Validator\DeliveryExecution\Constrains\ItemIdInCurrentStep;
use App\Validator\DeliveryExecution\Constrains\ResponseIdInCurrentStep;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GetAttachmentsDownloadUploadUrlValidator extends AbstractDeliveryExecutionAwareRequestValidator
{
    public function __construct(
        ValidatorInterface $validator,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
    ) {
        parent::__construct($validator);
    }

    protected function getRequestData(Request $request): array
    {
        return [
            'itemId' => $request->get('item_id'),
            'responseId' => $request->get('response_id'),
            'replace' => filter_var($request->get('replace', false), FILTER_VALIDATE_BOOL),
        ];
    }

    protected function getRequestValidationConstraint(): Constraint
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->getDeliveryExecution());
        return new Collection(
            [
                'itemId' => [
                    new Type(['type' => 'string']),
                    new NotBlank(),
                    new ItemIdInCurrentStep($testSession->getCurrentAssessmentItemRef()->getIdentifier()),
                ],
                'responseId' => [
                    new Type(['type' => 'string']),
                    new NotBlank(),
                    new ResponseIdInCurrentStep($testSession->getCandidateState()),
                ],
                'replace' => [
                    new Type(['type' => 'bool']),
                ],
            ],
        );
    }
}
