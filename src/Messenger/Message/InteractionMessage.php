<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

class InteractionMessage
{
    private const VERSION = '0.2.0';

    public function __construct(
        private readonly string $deliveryExecutionId,
        private readonly string $deliveryId,
        private readonly string $tenantId,
        private readonly int $deliveryExecutionStartedAt, // @deprecated, @see $durationInSeconds
        private readonly int $durationInSeconds,
        private readonly ?string $ipAddress,
        private readonly array $position = [],
        private readonly ?float $progressPercentage = 0.0,
        private readonly string $title = '',
        private readonly int $questions = 0,
        private readonly int $questionsViewed = 0,
        private readonly int $answered = 0,
        private readonly int $flagged = 0,
        private readonly int $viewed = 0,
        private readonly ?int $deliveryExecutionFinishedAt = null,
        private readonly ?string $userId = null,
        private readonly ?string $userName = null,
        private readonly ?string $deliveryExecutionStatus = null,
        private readonly ?array $positionDetails = null,
        private readonly ?bool $hasTimers = null,
        private readonly ?string $batteryId = null,
        private readonly ?string $batteryName = null,
        private readonly ?string $locale = null,
    ) {
    }

    public function getVersion(): string
    {
        return static::VERSION;
    }

    public function getPayload(): array
    {
        return [
            'position' => $this->position,
            'ipAddress' => $this->ipAddress,
            'title' => $this->title,
            'questions' => $this->questions,
            'questionsViewed' => $this->questionsViewed,
            'answered' => $this->answered,
            'flagged' => $this->flagged,
            'viewed' => $this->viewed,
            'tenantId' => $this->tenantId,
            'progressPercentage' => $this->progressPercentage,
            'durationInSeconds' => $this->durationInSeconds,
            'deliveryExecution' => $this->getDeliveryExecution(),
            'user' => $this->getUser(),
            'positionDetails' => $this->positionDetails,
            'isTimerExists' => $this->hasTimers,
            'battery' => $this->getBattery(),
            'locale' => $this->locale,
        ];
    }

    public function getDeliveryExecutionId(): string
    {
        return $this->deliveryExecutionId;
    }

    private function getDeliveryExecution(): array
    {
        return [
            'id' => $this->deliveryExecutionId,
            'startTs' => $this->convertDate($this->deliveryExecutionStartedAt),
            'endTs' => $this->convertDate($this->deliveryExecutionFinishedAt),
            'status' => $this->deliveryExecutionStatus,
            'delivery' => [
                'id' => $this->deliveryId,
            ],
        ];
    }

    private function getUser(): array
    {
        return [
            'id' => $this->userId,
            'name' => $this->userName,
        ];
    }

    private function getBattery(): ?array
    {
        if (null === $this->batteryId) {
            return null;
        }

        return [
            'id' => $this->batteryId,
            'name' => $this->batteryName,
        ];
    }

    private function convertDate(?int $time): ?string
    {
        if (!$time) {
            return $time;
        }
        return gmdate('c', $time);
    }
}
