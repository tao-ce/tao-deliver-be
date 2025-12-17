<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Factory;

use App\Lti\LtiCustomSettings;
use OAT\Bundle\QtiBundle\Manager\SessionManager;
use qtism\data\AssessmentSection;
use qtism\data\SectionPart;
use qtism\data\TestPart;
use qtism\runtime\tests\AssessmentItemSessionStore;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\RouteItem;

class AssessmentTestSessionFactory
{
    private int $changeCount = 0;

    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly LtiCustomSettings $ltiCustomSettings,
    ) {
    }

    public function createByLtiLaunchParams(
        AssessmentTestSession $currentTestSession,
        array $ltiLaunchParameters,
        bool $initAllItems = false,
    ): AssessmentTestSession {
        $this->changeCount = 0;

        if (!$this->ltiCustomSettings->isAllItemsEnabled($ltiLaunchParameters) && !$initAllItems) {
            return $currentTestSession;
        }

        $position = $currentTestSession->getRoute()->getPosition();
        $assessmentTest = $currentTestSession->getAssessmentTest();
        $assessmentItemSessionStore = $currentTestSession->getAssessmentItemSessionStore();

        /** @var TestPart $testPart */
        foreach ($assessmentTest->getTestParts() as $testPart) {
            /** @var AssessmentSection $assessmentSection */
            foreach ($testPart->getAssessmentSections() as $assessmentSection) {
                $this->removeAllSectionShuffleAndSelectionBySectionPart($assessmentSection);
            }
        }

        if ($this->mustRecreateRoute()) {
            $currentTestSession->setRoute($this->sessionManager->recreateRoute($assessmentTest));
            $this->populateMissingItemSessionStore($currentTestSession, $assessmentItemSessionStore);

            $currentTestSession->getRoute()->setPosition($position);
        }

        return $currentTestSession;
    }

    private function removeAllSectionShuffleAndSelectionBySectionPart(SectionPart $sectionPart): void
    {
        if ($sectionPart instanceof AssessmentSection) {
            $this->removeSectionSelection($sectionPart);
        }

        /** @var AssessmentSection $sectionPart */
        foreach ($sectionPart->getSectionParts() as $sectionPart) {
            if ($sectionPart instanceof AssessmentSection) {
                $this->removeSectionSelection($sectionPart);
                $this->removeAllSectionShuffleAndSelectionBySectionPart($sectionPart);
            }
        }
    }

    private function removeSectionSelection(AssessmentSection $section): void
    {
        if ($section->getSelection()) {
            $section->setSelection(null);

            $this->changeCount++;
        }

        if ($section->hasOrdering() && $section->getOrdering()->getShuffle()) {
            $section->setOrdering(null);

            $this->changeCount++;
        }
    }

    private function populateMissingItemSessionStore(
        AssessmentTestSession $session,
        AssessmentItemSessionStore $store,
    ): void {
        /** @var RouteItem $routeItem */
        foreach ($session->getRoute()->getAllRouteItems() as $routeItem) {
            if (!$store->hasAssessmentItemSession($routeItem->getAssessmentItemRef())) {
                $session->reinitializeAssessmentItemSession($routeItem);
            }
        }
    }

    private function mustRecreateRoute(): bool
    {
        return $this->changeCount > 0;
    }
}
