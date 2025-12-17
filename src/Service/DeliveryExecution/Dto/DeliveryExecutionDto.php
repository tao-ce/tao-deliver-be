<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution\Dto;

class DeliveryExecutionDto
{
    private const FIELD_DELIVERY_EXECUTION_ID = 'deliveryExecutionId';
    private const FIELD_LTI_LAUNCH_PARAMETERS = 'ltiLaunchParameters';
    private const FIELD_QTI_SDK_ENCODED_TEST_SESSION = 'qtiSdkEncodedTestSession';
    private const FIELD_EXTRA_STATE_DATA = 'extraStateData';
    private const FIELD_STATUS = 'status';
    private const FIELD_STARTED_AT = 'startedAt';
    private const FIELD_FINISHED_AT = 'finishedAt';
    private const FIELD_CLOSE_AT = 'closeAt';
    private const FIELD_UPDATED_AT = 'updatedAt';
    private const FIELD_LOCALE = 'locale';

    public function __construct(
        public readonly string $deliverExecutionId,
        public readonly array $ltiLaunchParameters,
        public readonly ?string $qtiSdkEncodedTestSession,
        public readonly ?array $extraStateData,
        public readonly string $status,
        public readonly string $startedAt,
        public readonly ?string $finishedAt,
        public readonly ?string $closeAt,
        public readonly ?string $updatedAt,
        public readonly ?string $locale = null,
    ) {
    }

    public static function createFromArray(array $data): self
    {
        return new self(
            $data[self::FIELD_DELIVERY_EXECUTION_ID],
            $data[self::FIELD_LTI_LAUNCH_PARAMETERS],
            $data[self::FIELD_QTI_SDK_ENCODED_TEST_SESSION] ?? null,
            $data[self::FIELD_EXTRA_STATE_DATA] ?? null,
            $data[self::FIELD_STATUS],
            $data[self::FIELD_STARTED_AT],
            $data[self::FIELD_FINISHED_AT] ?? null,
            $data[self::FIELD_CLOSE_AT] ?? null,
            $data[self::FIELD_UPDATED_AT] ?? null,
            $data[self::FIELD_LOCALE] ?? null,
        );
    }
}
