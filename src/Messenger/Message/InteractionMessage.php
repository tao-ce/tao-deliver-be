<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;

readonly class InteractionMessage
{
    private const VERSION = '0.2.0';

    public function __construct(
        private LtiResourceLinkInterface $resourceLink,
        private string $deliveryExecutionId,
        private string $deliveryId,
        private string $tenantId,
        private int $deliveryExecutionStartedAt, // @deprecated, @see $durationInSeconds
        private int $durationInSeconds,
        private ?string $ipAddress,
        private array $position = [],
        private ?float $progressPercentage = 0.0,
        private string $title = '',
        private int $questions = 0,
        private int $questionsViewed = 0,
        private int $answered = 0,
        private int $flagged = 0,
        private int $viewed = 0,
        private ?int $deliveryExecutionFinishedAt = null,
        private ?string $userName = null,
        private ?string $deliveryExecutionStatus = null,
        private ?array $positionDetails = null,
        private ?bool $hasTimers = null,
        private ?string $batteryId = null,
        private ?string $batteryName = null,
        private ?string $locale = null,
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
            'resourceLinkId' => $this->resourceLink->getIdentifier(),
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
        $deliveryExecutionIdKeyInfo = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo(
            $this->deliveryExecutionId,
        );

        return [
            'id' => $deliveryExecutionIdKeyInfo?->getUserId() ?? $deliveryExecutionIdKeyInfo?->getOriginalUserId(),
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
