<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution\Constrains;

use Symfony\Component\Validator\Constraint;
use qtism\runtime\common\State;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ResponseIdInCurrentStep extends Constraint
{
    public const NO_RESPONSE_IN_SESSION = '5b362284b5642b0475cac17e4e723d7a';

    protected const ERROR_NAMES = [
        self::NO_RESPONSE_IN_SESSION => 'NO_ITEM_IN_SESSION',
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct(
        public State $state,
        public string $message = 'This value should be contain in session for current step',
        ?array $groups = null,
        mixed $payload = null,
        array $options = [],
    ) {
        $options['state'] = $this->state;
        parent::__construct($options, $groups, $payload);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption(): string
    {
        return 'state';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredOptions(): array
    {
        return ['state'];
    }
}
