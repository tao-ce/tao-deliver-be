<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use App\Domain\DeliveryExecution\Model\ExtraStateData\OverallComment;
use App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;
use Carbon\Carbon;

class DeliveryExecutionExtraStateData
{
    use SubData\AliasIdTrait;
    use SubData\AssessmentEventsTrait;
    use SubData\AttachmentsTrait;
    use SubData\AttemptTrait;
    use SubData\CommentsTrait;
    use SubData\DeliveryPublicationTimeTrait;
    use SubData\DurationStorageTrait;
    use SubData\ExternalScoredTrait;
    use SubData\ExternalTimerDefinitionTrait;
    use SubData\FlaggedItemsTrait;
    use SubData\FlaggedTrait;
    use SubData\InitialStartTimestampTrait;
    use SubData\ItemStateTrait;
    use SubData\ManuallyGradedItemsTrait;
    use SubData\MultiLanguageOptionTrait;
    use SubData\OriginalSessionTrait;
    use SubData\OverallReviewCommentsTrait;
    use SubData\PlagiarismReportTrait;
    use SubData\RequestIpTrait;
    use SubData\TimerDependencyTrait;
    use SubData\ToolStateTrait;
    use SubData\TraceDataTrait;
    use SubData\UiEventsTrait;

    public static function fromArray(array $data): self
    {
        $extraStateData = new self();
        $extraStateData->aliasId = $data['aliasId'] ?? null;
        $extraStateData->assessmentEvents = $data['assessmentEvents'] ?? [];
        $extraStateData->attachments = $data['attachments'] ?? [];
        $extraStateData->attempt = $data['attempt'] ?? 0;
        $extraStateData->comments = $data['comments'] ?? [];
        $extraStateData->deliveryPublicationTime = isset($data['deliveryPublicationTime']) ? Carbon::createFromTimestamp($data['deliveryPublicationTime']) : null;
        $extraStateData->durationStorage = $extraStateData->fromArrayDurationStorage($data);
        $extraStateData->externalScoredItems = array_flip($data['externalScoredItems'] ?? []);
        $extraStateData->externalTimerDefinition = $extraStateData->fromArrayTimerDefinition($data ?? []);
        $extraStateData->flaggedItems = array_flip($data['flaggedItems'] ?? []);
        $extraStateData->hasTimer = $data['hasTimer'] ?? null;
        $extraStateData->initialStartTimestamp = $data['initialStartTimestamp'] ?? 0;
        $extraStateData->isFlagged = $data['isFlagged'] ?? false;
        $extraStateData->isMultilanguage = $data['isMultilanguage'] ?? false;
        $extraStateData->itemStates = $data['itemStates'] ?? [];
        $extraStateData->itemsOverallComments = isset($data['itemsOverallComments']) ? array_map(
            [OverallComment::class, 'fromArray'],
            json_decode($data['itemsOverallComments'], true),
        ) : [];
        $extraStateData->mainLocale = $data['mainLocale'] ?? null;
        $extraStateData->originalSession = $data['originalSession'] ?? null;
        $extraStateData->plagiarismReports = $extraStateData->fromArrayPlagiarismReports(
            $data['plagiarismReports'] ?? [],
        );
        $extraStateData->requestIp = $data['requestIp'] ?? null;
        $extraStateData->temporaryItemStates = $data['temporaryItemStates'] ?? [];
        $extraStateData->toolStates = $data['toolStates'] ?? [];
        $extraStateData->traceData = $data['traceData'] ?? [];
        $extraStateData->uiEvents = isset($data['uiEvents']) ? json_decode($data['uiEvents'], true) : [];

        foreach (self::createManuallyGradedItem($data['finalManuallyGradedItems'] ?? []) as $finalManuallyGradedItem) {
            $extraStateData = $extraStateData->withFinalManuallyGradedItem(...$finalManuallyGradedItem);
        }
        foreach (self::createManuallyGradedItem($data['initialManuallyGradedItems'] ?? []) as $initialManuallyGradedItem) {
            $extraStateData = $extraStateData->withInitialManuallyGradedItem(...$initialManuallyGradedItem);
        }

        return $extraStateData;
    }

    public function toArray(): array
    {
        return [
            'aliasId' => $this->getAliasId(),
            'assessmentEvents' => $this->getAssessmentEvents(),
            'attachments' => $this->attachments,
            'attempt' => $this->getAttempt(),
            'comments' => $this->getComments(),
            'deliveryPublicationTime' => $this->getDeliveryPublicationTime()?->getTimestamp(),
            'durationStorage' => $this->toArrayDurationStorage(),
            'externalScoredItems' => array_keys($this->toArrayExternalScoredItems()),
            'externalTimerDefinition' => $this->toArrayTimerDefinition(),
            'finalManuallyGradedItems' => $this->finalManuallyGradedItems,
            'flaggedItems' => $this->getFlaggedItems(),
            'hasTimer' => $this->hasTimer(),
            'initialManuallyGradedItems' => $this->initialManuallyGradedItems,
            'initialStartTimestamp' => $this->getInitialStartTimestamp(),
            'isFlagged' => $this->isFlagged(),
            'isMultilanguage' => $this->isMultilanguage(),
            'itemStates' => $this->getItemStates(),
            'itemsOverallComments' => json_encode($this->getArrayItemOverallComments()),
            'mainLocale' => $this->getMainLocale(),
            'originalSession' => $this->getOriginalSession(),
            'plagiarismReports' => $this->toArrayPlagiarismReports(),
            'requestIp' => $this->getRequestIp(),
            'temporaryItemStates' => $this->getTemporaryItemStates(),
            'toolStates' => $this->getToolStates(),
            'traceData' => $this->getTraceData(),
            'uiEvents' => json_encode($this->getUiEvents()),
        ];
    }

    public function diff(array $compareTo): array
    {
        $data = $this->toArray();

        return array_keys(
            array_diff_key(
                $data,
                array_uintersect_assoc(
                    $compareTo,
                    $data,
                    static fn($a, $b) => (int)($a !== $b),
                ),
            ),
        );
    }

    private static function createManuallyGradedItem($manuallyGradedItemsData): iterable
    {
        foreach ($manuallyGradedItemsData as $itemId => $timestamp) {
            yield [$itemId, $timestamp];
        }
    }
}
