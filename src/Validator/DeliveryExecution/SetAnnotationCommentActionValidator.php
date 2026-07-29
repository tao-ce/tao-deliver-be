<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Service\Lti\LtiTokenResolverInterface;
use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SetAnnotationCommentActionValidator extends AbstractRequestValidator
{
    public function __construct(
        private readonly LtiTokenResolverInterface $ltiTokenResolver,
        ValidatorInterface $validator,
    ) {
        parent::__construct($validator);
    }

    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    /**
     * @return array|Constraint[]
     */
    protected function getRequestValidationConstraint(): array
    {
        return [
            new NotBlank(),
            new Collection([
                'itemId' => [
                    new NotBlank(),
                    new Required(),
                    new Type(['type' => 'string']),
                ],
                'annotations' => [
                    new Required(),
                    new Type(['type' => 'array']),
                ],
            ]),
        ];
    }

    public function validateTokenRoles(): bool
    {
        return $this->ltiTokenResolver->hasOneOfRoles([
            LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR,
        ]);
    }
}
