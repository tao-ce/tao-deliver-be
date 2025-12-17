<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\Invalidation;
use App\Helper\Date;
use App\Service\DeliveryExecution\Dto\DeliveryExecutionDto;
use Carbon\Carbon;
use DateTimeInterface;
use DomainException;
use Exception;

class DeliveryExecutionFactory
{
    public static function create(
        string $id,
        array $ltiLaunchParameters,
        ?string $qtiSdkEncodedTestSession,
        ?DeliveryExecutionExtraStateData $extraStateData = null,
        ?string $status = DeliveryExecution::STATUS_INITIAL,
        ?DateTimeInterface $startedAt = null,
        ?DateTimeInterface $finishedAt = null,
        ?DateTimeInterface $closeAt = null,
        ?DateTimeInterface $updatedAt = null,
        ?InlineFeedbackCollection $reviewInlineComment = null,
        bool $isDeleted = false,
        ?string $locale = null,
        ?Invalidation $invalidation = null,
        ?string $initiallyScoredQtiSdkEncodedTestSession = null,
    ): DeliveryExecution {
        try {
            [$tenantId, $attempt, $deliveryId] = array_reverse(
                explode(DeliveryExecution::DOCUMENT_KEY_DELIMITER, $id),
            );
        } catch (Exception $exception) {
            throw new DomainException(
                sprintf(
                    'The delivery execution ID %s is not formatted as expected (%s)',
                    $id,
                    implode(
                        DeliveryExecution::DOCUMENT_KEY_DELIMITER,
                        [
                            '<user-id>',
                            '<delivery-id>',
                            '<attempt-id>',
                            '<tenant-id>',
                        ],
                    ),
                ),
                $exception->getCode(),
                $exception,
            );
        }

        return new DeliveryExecution(
            $id,
            $deliveryId,
            $tenantId,
            $startedAt ?? Carbon::now(),
            $ltiLaunchParameters,
            $qtiSdkEncodedTestSession,
            $extraStateData,
            $status,
            $finishedAt,
            $closeAt,
            $updatedAt,
            $isDeleted,
            $reviewInlineComment,
            locale: $locale,
            invalidation: $invalidation,
            initiallyScoredQtiSdkEncodedTestSession: $initiallyScoredQtiSdkEncodedTestSession,
        );
    }

    public function createFromDeliveryExecutionDto(
        DeliveryExecutionDto $deliveryExecutionDto,
    ): DeliveryExecution {
        $extraStateData = null;
        if ($deliveryExecutionDto->extraStateData !== null) {
            $extraStateData = DeliveryExecutionExtraStateData::fromArray($deliveryExecutionDto->extraStateData);
        }
        return self::create(
            $deliveryExecutionDto->deliverExecutionId,
            $deliveryExecutionDto->ltiLaunchParameters,
            $deliveryExecutionDto->qtiSdkEncodedTestSession,
            $extraStateData,
            $deliveryExecutionDto->status,
            Date::createFromDefaultFormat($deliveryExecutionDto->startedAt),
            Date::createFromDefaultFormat($deliveryExecutionDto->finishedAt),
            Date::createFromDefaultFormat($deliveryExecutionDto->closeAt),
            Date::createFromDefaultFormat($deliveryExecutionDto->updatedAt),
            $deliveryExecutionDto->locale,
        );
    }
}
