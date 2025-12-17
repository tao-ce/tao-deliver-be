<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Log;

use App\Registry\LoggerRegistry;
use App\Validator\AbstractRequestValidator;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateLogsActionRequestValidator extends AbstractRequestValidator
{
    public const FIELD_TYPE = 'type';
    public const FIELD_MESSAGE = 'message';
    public const FIELD_LEVEL = 'level';
    public const FIELD_CONTEXT = 'context';

    /** @var LoggerRegistry */
    private $loggerRegistry;

    public function __construct(ValidatorInterface $validator, LoggerRegistry $loggerRegistry)
    {
        parent::__construct($validator);

        $this->loggerRegistry = $loggerRegistry;
    }

    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    protected function getRequestValidationConstraint()
    {
        return [
            new NotBlank(),
            new All([
                new Collection([
                    self::FIELD_TYPE => [
                        new Optional([
                            new Type(['type' => 'string']),
                            new Choice(['choices' => $this->loggerRegistry->getAvailableChannels()]),
                        ]),
                    ],
                    self::FIELD_MESSAGE => [
                        new NotBlank(),
                        new Type(['type' => 'string']),
                    ],
                    self::FIELD_LEVEL => [
                        new NotBlank(),
                        new Type(['type' => 'string']),
                        new Choice(['choices' => $this->getPsrLogLevels()]),
                    ],
                    self::FIELD_CONTEXT => [
                        new Optional(new Type(['type' => 'array'])),
                    ],
                ]),
            ]),
        ];
    }

    private function getPsrLogLevels(): array
    {
        return [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];
    }
}
