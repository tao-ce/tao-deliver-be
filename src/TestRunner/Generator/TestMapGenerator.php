<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Generator;

use App\DataStore\Sender\DataStoreResultsSender;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Qti\Extractor\ItemResponseStatusResolver;
use App\TestItemAttachment\Service\ItemCategoryBasedAttachmentRegistry;
use App\TestRunner\Normalizer\TimeConstraintNormalizer;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentSection;
use qtism\data\ExtendedAssessmentItemRef;
use qtism\data\NavigationMode;
use qtism\data\SectionPart;
use qtism\data\SubmissionMode;
use qtism\data\TestPart;
use qtism\runtime\common\Variable;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\DurationStore;
use qtism\runtime\tests\RouteItem;
use qtism\runtime\tests\TimeConstraint;

class TestMapGenerator
{
    private const READ_ALOUD_ITEM_PLUGIN_CATEGORY = 'x-tao-option-tts';

    /** @var string[] */
    private array $checkedSections;

    public function __construct(
        private readonly ItemCategoryBasedAttachmentRegistry $attachmentRegistry,
        private readonly TimeConstraintNormalizer $timeConstraintNormalizer,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly ItemResponseStatusResolver $itemResponseStatusResolver,
    ) {
    }

    public function generate(
        AssessmentTestSession $testSession,
        DeliveryExecution $deliveryExecution,
        bool $generateReviewableMap = false,
    ): array {
        $this->checkedSections = [];
        $parts = [];
        $offset = 0;
        $stats = $this->getDefaultStats();
        $isTestSessionRunning = $testSession->isRunning();
        $attachments = $this->attachmentRegistry->resolveAttachments(
            $deliveryExecution->getTenantId(),
            $testSession->getRoute()->getCategories()->getArrayCopy(),
        );

        /** @var RouteItem $routeItem */
        foreach ($testSession->getRoute()->getAllRouteItems() as $routeItem) {
            $testPart = $routeItem->getTestPart();
            $partIdentifier = $routeItem->getTestPart()->getIdentifier();
            $sectionIdentifier = $routeItem->getAssessmentSection()->getIdentifier();
            $itemIdentifier = $routeItem->getAssessmentItemRef()->getIdentifier();
            $durationStore = $testSession->getDurationStore();
            $navigationMode = $generateReviewableMap ? NavigationMode::NONLINEAR : $testPart->getNavigationMode();
            $submissionMode = $testPart->getSubmissionMode();

            [$allowSkippingPart, $allowSkippingSection, $allowSkippingItem] = $generateReviewableMap ? [true, true, true] : $this->getAllowSkipping($routeItem, $isTestSessionRunning);
            [$validateResponsesPart, $validateResponsesSection, $validateResponsesItem] = $this->getValidateResponses($routeItem, $isTestSessionRunning);
            [$maxAttemptsPart, $maxAttemptsSection, $maxAttemptsItem] = $generateReviewableMap ? [-1, -1, -1] : $this->getMaxAttempts($routeItem, $isTestSessionRunning);

            if (!array_key_exists($partIdentifier, $parts)) {
                $parts = $this->addPartInformation(
                    $testSession,
                    $durationStore,
                    $testPart,
                    $parts,
                    $partIdentifier,
                    $offset,
                    $navigationMode,
                    $submissionMode,
                    $allowSkippingPart,
                    $validateResponsesPart,
                    $maxAttemptsPart,
                );
            }

            if (empty($parts[$partIdentifier]['isAdaptive'])) {
                $parts[$partIdentifier]['isAdaptive'] = $this->isAdaptive($routeItem);
            }

            if (!array_key_exists($sectionIdentifier, $parts[$partIdentifier]['sections'])) {
                $parts = $this->addSectionInformation(
                    $testSession,
                    $durationStore,
                    $routeItem,
                    $parts,
                    $sectionIdentifier,
                    $offset,
                    $partIdentifier,
                    $navigationMode,
                    $allowSkippingSection,
                    $validateResponsesSection,
                    $maxAttemptsSection,
                );
            }

            $itemSession = $testSession->getAssessmentItemSessionStore()->getAssessmentItemSession(
                $routeItem->getAssessmentItemRef(),
                $routeItem->getOccurence(),
            );

            $parts = $this->addItemInformation(
                $testSession,
                $deliveryExecution,
                $durationStore,
                $routeItem,
                $itemSession,
                $attachments,
                $deliveryExecution->hasItemState($itemIdentifier),
                $parts,
                $itemIdentifier,
                $offset,
                $navigationMode,
                $sectionIdentifier,
                $partIdentifier,
                $allowSkippingItem,
                $validateResponsesItem,
                $maxAttemptsItem,
            );
            $offset++;

            if (!$this->isItemInformational($routeItem->getAssessmentItemRef())) {
                $stats['questions']++;
                $parts[$partIdentifier]['sections'][$sectionIdentifier]['stats']['questions']++;

                if ($parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['answered']) {
                    $stats['answered']++;
                    $parts[$partIdentifier]['stats']['answered']++;
                    $parts[$partIdentifier]['sections'][$sectionIdentifier]['stats']['answered']++;
                }

                if ($parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['viewed']) {
                    $stats['questionsViewed']++;
                    $parts[$partIdentifier]['stats']['questionsViewed']++;
                    $parts[$partIdentifier]['sections'][$sectionIdentifier]['stats']['questionsViewed']++;
                }
            }

            if ($parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['flagged']) {
                $stats['flagged']++;
                $parts[$partIdentifier]['stats']['flagged']++;
                $parts[$partIdentifier]['sections'][$sectionIdentifier]['stats']['flagged']++;
            }

            if ($parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['viewed']) {
                $stats['viewed']++;
                $parts[$partIdentifier]['stats']['viewed']++;
                $parts[$partIdentifier]['sections'][$sectionIdentifier]['stats']['viewed']++;
            }

            $stats['total']++;
            $parts[$partIdentifier]['stats']['total']++;
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['stats']['total']++;
        }

        $scoresExtractor = new ScoresExtractor($testSession);

        return [
            'scope' => 'test',
            'scoreOutcomes' => $scoresExtractor->extractScoreOutcomes(),
            'stats' => $stats,
            'parts' => $parts,
            'identifier' => $testSession->getAssessmentTest()->getIdentifier(),
            'title' => $testSession->getAssessmentTest()->getTitle(),
        ];
    }

    private function getPartTimeConstraints(
        AssessmentTestSession $testSession,
        DurationStore $durationStore,
        TestPart $testPart,
        string $identifier,
    ): ?array {
        return $durationStore->offsetExists($identifier)
            ? $this->timeConstraintNormalizer->normalize(
                $testSession,
                new TimeConstraint($testPart, $durationStore[$identifier], $testPart->getNavigationMode()),
            ) : [];
    }

    private function getSectionTimeConstraints(
        AssessmentTestSession $testSession,
        DurationStore $durationStore,
        AssessmentSection $assessmentSection,
        string $identifier,
        int $navigationMode,
    ): ?array {
        return $durationStore->offsetExists($identifier)
            ? $this->timeConstraintNormalizer->normalize(
                $testSession,
                new TimeConstraint($assessmentSection, $durationStore[$identifier], $navigationMode),
            ) : [];
    }

    private function getItemTimeConstraints(
        AssessmentTestSession $testSession,
        DurationStore $durationStore,
        AssessmentItemRef $assessmentItemRef,
        string $identifier,
        int $navigationMode,
    ): ?array {
        return $durationStore->offsetExists($identifier)
            ? $this->timeConstraintNormalizer->normalize(
                $testSession,
                new TimeConstraint($assessmentItemRef, $durationStore[$identifier], $navigationMode),
            ) : [];
    }

    private function getDefaultStats(): array
    {
        return [
            'questionsViewed' => 0,
            'questions' => 0,
            'answered' => 0,
            'flagged' => 0,
            'viewed' => 0,
            'total' => 0,
        ];
    }

    private function addPartInformation(
        AssessmentTestSession $testSession,
        DurationStore $durationStore,
        TestPart $testPart,
        array $parts,
        string $partIdentifier,
        int $partCount,
        int $navigationMode,
        int $submissionMode,
        ?bool $allowSkipping,
        ?bool $validateResponses,
        ?int $maxAttempts,
    ): array {
        $parts[$partIdentifier] = [
            'id' => $partIdentifier,
            'label' => $partIdentifier,
            'position' => $partCount,
            'isLinear' => $navigationMode === NavigationMode::LINEAR,
            'isIndividual' => $submissionMode === SubmissionMode::INDIVIDUAL,
            'timeConstraint' => $this->getPartTimeConstraints($testSession, $durationStore, $testPart, $partIdentifier),
            'stats' => $this->getDefaultStats(),
            'sections' => [],
        ];

        if ($allowSkipping !== null) {
            $parts[$partIdentifier]['allowSkipping'] = $allowSkipping;
        }

        if ($validateResponses !== null) {
            $parts[$partIdentifier]['validateResponses'] = $validateResponses;
        }

        if ($maxAttempts !== null) {
            $parts[$partIdentifier]['maxAttempts'] = $maxAttempts;
        }

        return $parts;
    }

    private function addSectionInformation(
        AssessmentTestSession $testSession,
        DurationStore $durationStore,
        RouteItem $routeItem,
        array $parts,
        string $sectionIdentifier,
        int $sectionCount,
        string $partIdentifier,
        int $navigationMode,
        ?bool $allowSkipping,
        ?bool $validateResponses,
        ?int $maxAttempts,
    ): array {
        $parts[$partIdentifier]['sections'][$sectionIdentifier] = [
            'id' => $sectionIdentifier,
            'label' => $routeItem->getAssessmentSection()->getTitle(),
            'isCatAdaptive' => false,
            'position' => $sectionCount,
            'timeConstraint' => $this->getSectionTimeConstraints($testSession, $durationStore, $routeItem->getAssessmentSection(), $sectionIdentifier, $navigationMode),
            'stats' => $this->getDefaultStats(),
            'items' => [],
        ];

        if ($allowSkipping !== null) {
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['allowSkipping'] = $allowSkipping;
        }

        if ($validateResponses !== null) {
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['validateResponses'] = $validateResponses;
        }

        if ($maxAttempts !== null) {
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['maxAttempts'] = $maxAttempts;
        }

        return $parts;
    }

    private function enrichItemCategories(array $categories, array $getLtiLaunchParameters): array
    {
        if ($this->ltiCustomSettings->isReadAloudForceEnabled($getLtiLaunchParameters)) {
            $categories[] = self::READ_ALOUD_ITEM_PLUGIN_CATEGORY;
        }
        return $categories;
    }

    private function addItemInformation(
        AssessmentTestSession $testSession,
        DeliveryExecution $deliveryExecution,
        DurationStore $durationStore,
        RouteItem $routeItem,
        AssessmentItemSession $itemSession,
        array $attachments,
        bool $hasItemState,
        array $parts,
        string $itemIdentifier,
        int $itemCount,
        int $navigationMode,
        string $sectionIdentifier,
        string $partIdentifier,
        ?bool $allowSkipping,
        ?bool $validateResponses,
        ?int $maxAttempts,
    ): array {
        /** @var ExtendedAssessmentItemRef $assessmentItemRef */
        $assessmentItemRef = $routeItem->getAssessmentItemRef();
        $itemAttachments = [];

        foreach ($assessmentItemRef->getCategories() as $category) {
            if (isset($attachments[$category])) {
                $itemAttachments[] = $attachments[$category];
            }
        }

        $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier] = [
            'id' => $itemIdentifier,
            'label' => $assessmentItemRef->getTitle(),
            'position' => $itemCount,
            'occurrence' => $routeItem->getOccurence(),
            'remainingAttempts' => $itemSession->getRemainingAttempts(),
            'answered' => $this->itemResponseStatusResolver->isRespondedTo($itemSession, $deliveryExecution),
            'flagged' => $deliveryExecution->getExtraStateData()->isItemFlagged($itemIdentifier),
            'viewed' => $itemSession->isPresented(),
            'categories' => $this->enrichItemCategories(
                $assessmentItemRef->getCategories()->getArrayCopy(),
                $deliveryExecution->getLtiLaunchParameters(),
            ),
            'attachments' => $itemAttachments,
            'hasFeedbacks' => $itemSession->getSubmissionMode() === SubmissionMode::INDIVIDUAL
                && count($itemSession->getAssessmentItem()->getModalFeedbackRules()) > 0,
            'allowComment' => $itemSession->getItemSessionControl()->doesAllowComment(),
            'timeConstraint' => $this->getItemTimeConstraints(
                $testSession,
                $durationStore,
                $assessmentItemRef,
                $sectionIdentifier,
                $navigationMode,
            ),
            'informational' => $this->isItemInformational($assessmentItemRef),
            'hasItemState' => $hasItemState,
        ];

        $isExternalScoredEnabled = false;
        foreach ($assessmentItemRef->getOutcomeDeclarations() as $outcomeDeclaration) {
            if ($outcomeDeclaration->isScoredByHuman()) {
                $isExternalScoredEnabled = true;
                break;
            }
        }
        $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['externalScored'] = $isExternalScoredEnabled;

        if ($this->ltiCustomSettings->isReviewModeWithScore($deliveryExecution->getLtiLaunchParameters())) {

            /** @var Variable $variable */
            foreach ($itemSession->getAllVariables() as $variable) {
                if (DataStoreResultsSender::SCORE_ID === $variable->getIdentifier()) {
                    $score = $variable->getValue()->getValue();
                }

                if (DataStoreResultsSender::MAX_SCORE_ID === $variable->getIdentifier()) {
                    $maxScore = $variable->getValue()->getValue();
                }
            }

            $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['score'] =
                ($isExternalScoredEnabled
                    && !$deliveryExecution->isItemScoredExternally($itemIdentifier))
                ? null
                : ($score ?? null);
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['maxScore'] = $maxScore ?? null;
        }

        if ($allowSkipping !== null) {
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['allowSkipping'] = $allowSkipping;
        }

        if ($validateResponses !== null) {
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['validateResponses'] = $validateResponses;
        }

        if ($maxAttempts !== null) {
            $parts[$partIdentifier]['sections'][$sectionIdentifier]['items'][$itemIdentifier]['maxAttempts'] = $maxAttempts;
        }

        return $parts;
    }

    private function getAllowSkipping(RouteItem $routeItem, bool $isTestSessionRunning): array
    {
        if (!$isTestSessionRunning) {
            return [null, null, null];
        }

        return [
            $routeItem->getTestPart()->getItemSessionControl()
                ? $routeItem->getTestPart()->getItemSessionControl()->doesAllowSkipping()
                : null,
            $routeItem->getAssessmentSection()->getItemSessionControl()
                ? $routeItem->getAssessmentSection()->getItemSessionControl()->doesAllowSkipping()
                : null,
            $routeItem->getAssessmentItemRef()->getItemSessionControl()
                ? $routeItem->getAssessmentItemRef()->getItemSessionControl()->doesAllowSkipping()
                : null,
        ];
    }

    private function getValidateResponses(RouteItem $routeItem, bool $isTestSessionRunning): array
    {
        if (!$isTestSessionRunning) {
            return [null, null, null];
        }

        return [
            $routeItem->getTestPart()->getItemSessionControl()
                ? $routeItem->getTestPart()->getItemSessionControl()->mustValidateResponses()
                : null,
            $routeItem->getAssessmentSection()->getItemSessionControl()
                ? $routeItem->getAssessmentSection()->getItemSessionControl()->mustValidateResponses()
                : null,
            $routeItem->getAssessmentItemRef()->getItemSessionControl()
                ? $routeItem->getAssessmentItemRef()->getItemSessionControl()->mustValidateResponses()
                : null,
        ];
    }

    private function getMaxAttempts(RouteItem $routeItem, bool $isTestSessionRunning): array
    {
        if (!$isTestSessionRunning) {
            return [null, null, null];
        }

        return [
            $routeItem->getTestPart()->getItemSessionControl()
                ? $routeItem->getTestPart()->getItemSessionControl()->getMaxAttempts()
                : null,
            $routeItem->getAssessmentSection()->getItemSessionControl()
                ? $routeItem->getAssessmentSection()->getItemSessionControl()->getMaxAttempts()
                : null,
            $routeItem->getAssessmentItemRef()->getItemSessionControl()
                ? $routeItem->getAssessmentItemRef()->getItemSessionControl()->getMaxAttempts()
                : null,
        ];
    }

    private function isItemInformational(ExtendedAssessmentItemRef $itemRef): bool
    {
        return empty($itemRef->getResponseDeclarations()->getArrayCopy())
            || in_array('x-tao-itemusage-informational', $itemRef->getCategories()->getArrayCopy());
    }

    private function isAdaptive(RouteItem $routeItem): bool
    {
        if ($routeItem->getTestPart()->getNavigationMode() !== NavigationMode::LINEAR) {
            return false;
        }

        if ($this->hasBranchRulesOrPreConditions($routeItem->getAssessmentItemRef())) {
            return true;
        }

        if ($this->hasBranchRulesOrPreConditions($routeItem->getTestPart())) {
            return true;
        }

        /** @var AssessmentSection $section */
        foreach ($routeItem->getAssessmentSections() as $section) {
            if (in_array($section->getIdentifier(), $this->checkedSections, true)) {
                continue;
            }

            $this->checkedSections[] = $section->getIdentifier();

            if ($this->hasBranchRulesOrPreConditions($section)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param SectionPart|TestPart $element
     * @return bool
     */
    private function hasBranchRulesOrPreConditions($element): bool
    {
        $hasBranchRules = $element->getBranchRules()->count() > 0;
        $hasPreConditions = $element->getPreConditions()->count() > 0;

        return $hasBranchRules || $hasPreConditions;
    }
}
