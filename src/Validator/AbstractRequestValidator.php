<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator;

use App\Validator\Exception\RequestValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractRequestValidator
{
    /** @var ValidatorInterface */
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @throws RequestValidationException
     */
    public function getValidatedRequestParameters(Request $request): array
    {
        $requestData = $this->getRequestData($request);
        return $this->getValidateRequestData($requestData);
    }

    /**
     * @throws RequestValidationException
     */
    public function getValidateRequestData(array $requestData): array
    {
        $violations = $this->validator->validate($requestData, $this->getRequestValidationConstraint());

        if ($violations->count() > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            throw new RequestValidationException(implode(', ', $messages));
        }

        return $requestData;
    }

    /**
     * @throws RequestValidationException
     */
    public function getValidatedRequestParameter(Request $request, string $parameterName, mixed $defaultValue = null)
    {
        return $this->getValidatedRequestParameters($request)[$parameterName] ?? $defaultValue;
    }

    protected function extractRequestJsonContent(Request $request): array
    {
        $jsonContent = json_decode($request->getContent(), true);

        if (json_last_error()) {
            throw new RequestValidationException(sprintf(
                'Invalid JSON request body received. Error: %s',
                json_last_error_msg(),
            ));
        }

        return $jsonContent;
    }

    abstract protected function getRequestData(Request $request): array;

    /**
     * @return Constraint|Constraint[]
     */
    abstract protected function getRequestValidationConstraint();
}
