<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

use App\Messenger\Message\ItemExternalScoring\ItemResult;
use App\Messenger\Message\ItemExternalScoring\TestResult;
use App\Qti\Service\Contract\ArgumentAssessmentResultInterface;

class DeliveryExecutionItemExternalScoringMessage implements ArgumentAssessmentResultInterface, NormalizableInterface
{
    private string $contextSourcedId;
    private string $deliveryId;
    private string $userId;
    private TestResult $testResult;
    /** @var ItemResult[]  */
    private array $itemResultList;
    private ?self $initialResult = null;

    // TODO remove this method in favor of a proper normalizer usage
    //  objects should not control their own normalization
    public static function fromArray(array $data): static
    {
        $assessmentResult = $data['assessmentResult'];
        $message = new self();

        $message->contextSourcedId = $assessmentResult['contextSourcedId'];
        $message->userId = $assessmentResult['userId'];
        $message->deliveryId = $assessmentResult['deliveryId'];

        $message->testResult = TestResult::fromArray($assessmentResult['testResult']);
        $itemResultList = array_map(
            [ItemResult::class, 'fromArray'],
            $assessmentResult['itemResult'] ?? [],
        );
        $message->itemResultList = array_reduce($itemResultList, [self::class, 'mapItemResult']) ?: [];
        if (empty($assessmentResult['prevTestResult']) && empty($assessmentResult['prevItemResult'])) {
            return $message;
        }

        $initialResult = new self();
        $initialResult->testResult = TestResult::fromArray($assessmentResult['prevTestResult'] ?? []);
        $initialResult->itemResultList = [];
        if (!empty($assessmentResult['prevItemResult'])) {
            $previousItemResultList = array_map(
                [ItemResult::class, 'fromArray'],
                $assessmentResult['prevItemResult'],
            );
            $initialResult->itemResultList = array_reduce($previousItemResultList, [self::class, 'mapItemResult']) ?: [];
        }
        $message->initialResult = $initialResult;

        return $message;
    }

    public function getItemResultAssocList(): array
    {
        return $this->itemResultList;
    }

    public function getTestResult(): TestResult
    {
        return $this->testResult;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getInitialResult(): ?self
    {
        return $this->initialResult;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getContextSourcedId(): string
    {
        return $this->contextSourcedId;
    }

    private static function mapItemResult($itemResultList, ItemResult $itemResult)
    {
        $itemResultList[$itemResult->getId()] = $itemResult;
        return $itemResultList;
    }
}
