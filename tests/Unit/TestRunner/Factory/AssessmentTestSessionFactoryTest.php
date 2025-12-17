<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Factory;

use App\Lti\LtiCustomSettings;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use OAT\Bundle\QtiBundle\Manager\SessionManager;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\data\AssessmentSection;
use qtism\data\AssessmentTest;
use qtism\data\ExtendedAssessmentItemRef;
use qtism\data\rules\Ordering;
use qtism\data\rules\Selection;
use qtism\data\SectionPartCollection;
use qtism\data\TestPart;
use qtism\data\TestPartCollection;
use qtism\runtime\tests\AssessmentItemSessionStore;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\Route;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AssessmentTestSessionFactoryTest extends KernelTestCase
{
    private readonly AssessmentTestSessionFactory $subject;
    private readonly AssessmentTestSession|MockObject $testSessionMock;

    protected function setUp(): void
    {
        $this->setUpTestSessionMock();

        $this->subject = new AssessmentTestSessionFactory(
            static::getContainer()->get(SessionManager::class),
            static::getContainer()->get(LtiCustomSettings::class),
        );
    }

    public function testCreateByLtiLaunchParamsWillNotChangeSessionDueNotActiveClaims(): void
    {
        $this->assertSame(
            $this->testSessionMock,
            $this->subject->createByLtiLaunchParams($this->testSessionMock, []),
        );
    }

    public function testCreateByLtiLaunchParamsWillRemoveSectionSelectionAndShuffle(): void
    {
        $this->subject->createByLtiLaunchParams(
            $this->testSessionMock,
            [
                'custom' => [
                    LtiCustomSettings::PARAM_ALL_ITEMS => true,
                ],
            ],
        );

        /** @var TestPart $testPart */
        foreach ($this->testSessionMock->getAssessmentTest()->getTestParts() as $testPart) {
            /** @var AssessmentSection $assessmentSection */
            foreach ($testPart->getAssessmentSections() as $assessmentSection) {
                $this->assertNull($assessmentSection->getSelection());
                $this->assertNull($assessmentSection->getOrdering());

                /** @var AssessmentSection $assessmentSubSection */
                foreach ($assessmentSection->getSectionParts() as $assessmentSubSection) {
                    $this->assertNull($assessmentSubSection->getSelection());
                    $this->assertNull($assessmentSubSection->getOrdering());
                }
            }
        }
    }

    private function setUpTestSessionMock(): void
    {
        $assessmentSection1 = new AssessmentSection('section-1', 'section-1', true);
        $assessmentSection1->setOrdering(new Ordering(true));
        $assessmentSection1->setSelection(new Selection(1));
        $assessmentSection1->setSectionParts(
            new SectionPartCollection(
                [
                    new ExtendedAssessmentItemRef('item-1', 'item-1-href'),
                ],
            ),
        );
        $assessmentSection2 = new AssessmentSection('section-2', 'section-2', true);
        $assessmentSection2->setOrdering(new Ordering(true));
        $assessmentSection2->setSelection(new Selection(1));
        $assessmentSection2->setSectionParts(
            new SectionPartCollection(
                [
                    new ExtendedAssessmentItemRef('item-2', 'item-2-href'),
                ],
            ),
        );
        $assessmentParentSection = new AssessmentSection('section', 'section', true);
        $assessmentParentSection->setOrdering(new Ordering(true));
        $assessmentParentSection->setSelection(new Selection(1));
        $assessmentParentSection->setSectionParts(
            new SectionPartCollection(
                [
                    $assessmentSection1,
                    $assessmentSection2,
                ],
            ),
        );
        $test = new AssessmentTest(
            'test',
            'My test',
            new TestPartCollection(
                [
                    new TestPart(
                        'test-part',
                        new SectionPartCollection(
                            [
                                $assessmentParentSection,
                            ],
                        ),
                    ),
                ],
            ),
        );

        $route = $this->createMock(Route::class);
        $route->method('getPosition')->willReturn(0);

        $this->testSessionMock = $this->createMock(AssessmentTestSession::class);
        $this->testSessionMock->method('getRoute')->willReturn($route);
        $this->testSessionMock->method('getAssessmentTest')->willReturn($test);
        $this->testSessionMock->method('getAssessmentItemSessionStore')->willReturn(new AssessmentItemSessionStore());
    }
}
