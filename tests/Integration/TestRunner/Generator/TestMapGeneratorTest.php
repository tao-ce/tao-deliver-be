<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\Generator;

use App\Lti\LtiCustomSettings;
use App\Qti\Extractor\ItemResponseStatusResolver;
use App\TestItemAttachment\Service\ItemCategoryBasedAttachmentRegistry;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Generator\TestMapGenerator;
use App\TestRunner\Normalizer\TimeConstraintNormalizer;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TestMapGeneratorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;

    private ItemCategoryBasedAttachmentRegistry $attachmentRegistry;
    private TestMapGenerator $subject;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;

    public function setUp(): void
    {
        static::bootKernel();

        $this->attachmentRegistry = $this->createMock(ItemCategoryBasedAttachmentRegistry::class);
        $this->copyCompiledTestToStorage(['compact-test.xml'], 'BasicWithExternalScored');
        $this->deliveryExecutionPropertyService = self::getContainer()->get(DeliveryExecutionPropertyService::class);

        $this->subject = new TestMapGenerator(
            $this->attachmentRegistry,
            self::getContainer()->get(TimeConstraintNormalizer::class),
            self::getContainer()->get(LtiCustomSettings::class),
            $this->createMock(ItemResponseStatusResolver::class),
        );
    }

    public function testItGenerates(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicWithExternalScored#resultId#tenantId',
            'BasicWithExternalScored',
            'tenantId',
            [],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $testMap = $this->subject->generate($testSession, $deliveryExecution);
        $this->assertEquals($this->getExpectedOutput(), $testMap);
    }

    public function testItGeneratesForReview(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicWithExternalScored#resultId#tenantId',
            'BasicWithExternalScored',
            'tenantId',
            [],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $testMap = $this->subject->generate($testSession, $deliveryExecution, true);
        $this->assertEquals($this->getExpectedOutput(true), $testMap);
    }

    public function testItGeneratesForReviewWithScore(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicWithExternalScored#resultId#tenantId',
            'BasicWithExternalScored',
            'tenantId',
            ['custom' => [LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_SCORE => true]],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $testMap = $this->subject->generate($testSession, $deliveryExecution, true);
        $this->assertEquals($this->getExpectedOutput(true, true), $testMap);
    }

    public function testItGeneratesTestMapWithAttachments(): void
    {
        $this->copyCompiledTestToStorage(['compact-test.xml'], 'BasicWithAttachments');
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicWithAttachments#resultId#tenantId',
            'BasicWithAttachments',
            'tenantId',
            [],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $attachments = [
            'x-tao-attachment-2f428687-845a-4c24-b069-e0f0c449295a' => [
                'id' => '2f428687-845a-4c24-b069-e0f0c449295a',
                'url' => 'https://taotesting.com/cdn/file-1.pdf',
                'name' => 'file-1.pdf',
                'type' => 'application/pdf',
            ],
            'x-tao-attachment-95dce224-5ec8-4b20-a3d0-99f6d1935198' => [
                'id' => '95dce224-5ec8-4b20-a3d0-99f6d1935198',
                'url' => 'https://taotesting.com/cdn/file-3.pdf',
                'name' => 'file-3.pdf',
                'type' => 'application/pdf',
            ],
            'x-tao-attachment-55dce224-5ec8-4b20-a3d0-99f6d1935198' => [
                'id' => '55dce224-5ec8-4b20-a3d0-99f6d1935198',
                'url' => 'https://taotesting.com/cdn/file-2.pdf',
                'name' => 'file-2.pdf',
                'type' => 'application/pdf',
            ],
        ];
        $categories = array_keys($attachments);
        ksort($attachments);

        $this->attachmentRegistry
            ->expects($this->once())
            ->method('resolveAttachments')
            ->with('tenantId', $categories)
            ->willReturn($attachments);

        $testMap = $this->subject->generate($testSession, $deliveryExecution, true);
        $this->assertEquals(
            $this->getExpectedOutput(
                true,
                attachments: [
                    'Item-Q02' => [
                        'x-tao-attachment-2f428687-845a-4c24-b069-e0f0c449295a' => $attachments['x-tao-attachment-2f428687-845a-4c24-b069-e0f0c449295a'],
                    ],
                    'Item-Q03' => [
                        'x-tao-attachment-95dce224-5ec8-4b20-a3d0-99f6d1935198' => $attachments['x-tao-attachment-95dce224-5ec8-4b20-a3d0-99f6d1935198'],
                        'x-tao-attachment-55dce224-5ec8-4b20-a3d0-99f6d1935198' => $attachments['x-tao-attachment-55dce224-5ec8-4b20-a3d0-99f6d1935198'],
                    ],
                ],
            ),
            $testMap,
        );
    }

    private function getExpectedOutput(
        bool $reviewable = false,
        bool $showScore = false,
        array $attachments = [],
    ): array {
        $expectedMap = [
            'scope' => 'test',
            'stats' => [
                'questionsViewed' => 1,
                'questions' => 3,
                'answered' => 0,
                'flagged' => 0,
                'viewed' => 1,
                'total' => 3,
            ],
            'parts' => [
                'TestPart-TP01' =>
                    [
                        'id' => 'TestPart-TP01',
                        'label' => 'TestPart-TP01',
                        'position' => 0,
                        'isLinear' => !$reviewable,
                        'isIndividual' => true,
                        'allowSkipping' => true,
                        'validateResponses' => false,
                        'maxAttempts' => $reviewable ? -1 : 0,
                        'timeConstraint' => $this->getExpectedTimeConstraint(
                            'TestPart-TP01',
                            'testPart',
                            'TestPart-TP01',
                        ),
                        'stats' => [
                            'questionsViewed' => 1,
                            'questions' => 0,
                            'answered' => 0,
                            'flagged' => 0,
                            'viewed' => 1,
                            'total' => 3,
                        ],
                        'sections' => [
                            'Section-S01' => [
                                'id' => 'Section-S01',
                                'label' => 'Section 01',
                                'isCatAdaptive' => false,
                                'position' => 0,
                                'timeConstraint' => $this->getExpectedTimeConstraint(
                                    'Section-S01',
                                    'assessmentSection',
                                    'Section 01',
                                ),
                                'stats' => [
                                    'questionsViewed' => 1,
                                    'questions' => 3,
                                    'answered' => 0,
                                    'flagged' => 0,
                                    'viewed' => 1,
                                    'total' => 3,
                                ],
                                'items' => [
                                    'Item-Q01' => [
                                        'id' => 'Item-Q01',
                                        'label' => '',
                                        'position' => 0,
                                        'occurrence' => 0,
                                        'remainingAttempts' => -1,
                                        'answered' => false,
                                        'flagged' => false,
                                        'viewed' => true,
                                        'attachments' => array_values($attachments['Item-Q01'] ?? []),
                                        'categories' => array_keys($attachments['Item-Q01'] ?? []),
                                        'hasFeedbacks' => false,
                                        'allowComment' => false,
                                        'timeConstraint' => $this->getExpectedTimeConstraint('Item-Q01'),
                                        'informational' => false,
                                        'externalScored' => true,
                                        'hasItemState' => false,

                                    ],
                                    'Item-Q02' => [
                                        'id' => 'Item-Q02',
                                        'label' => '',
                                        'position' => 1,
                                        'occurrence' => 0,
                                        'remainingAttempts' => -1,
                                        'answered' => false,
                                        'flagged' => false,
                                        'viewed' => false,
                                        'attachments' => array_values($attachments['Item-Q02'] ?? []),
                                        'categories' => array_keys($attachments['Item-Q02'] ?? []),
                                        'hasFeedbacks' => false,
                                        'allowComment' => false,
                                        'timeConstraint' => $this->getExpectedTimeConstraint('Item-Q02'),
                                        'informational' => false,
                                        'externalScored' => true,
                                        'hasItemState' => false,
                                    ],
                                    'Item-Q03' => [
                                        'id' => 'Item-Q03',
                                        'label' => '',
                                        'position' => 2,
                                        'occurrence' => 0,
                                        'remainingAttempts' => -1,
                                        'answered' => false,
                                        'flagged' => false,
                                        'viewed' => false,
                                        'attachments' => array_values($attachments['Item-Q03'] ?? []),
                                        'categories' => array_keys($attachments['Item-Q03'] ?? []),
                                        'hasFeedbacks' => false,
                                        'allowComment' => false,
                                        'timeConstraint' => $this->getExpectedTimeConstraint('Item-Q03'),
                                        'informational' => false,
                                        'externalScored' => false,
                                        'hasItemState' => false,
                                    ],
                                ],
                            ],
                        ],
                        'isAdaptive' => false,
                    ],
            ],
            'identifier' => 'Test-T01',
            'title' => 'Basic Test (Linear-Individual)',
            'scoreOutcomes' => [
                'isPassed' => null,
            ],
        ];

        if ($reviewable) {
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['maxAttempts'] = -1;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['allowSkipping'] = true;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q01']['allowSkipping'] = true;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q02']['allowSkipping'] = true;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q03']['allowSkipping'] = true;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q01']['maxAttempts'] = -1;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q02']['maxAttempts'] = -1;
            $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q03']['maxAttempts'] = -1;

            if ($showScore) {
                // because Item-Q01 external score his score null till it calculated after
                $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q01']['score'] = null;
                $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q01']['maxScore'] = 1.0;
                $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q02']['score'] = null;
                $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q02']['maxScore'] = 1.0;
                $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q03']['score'] = 0.0;
                $expectedMap['parts']['TestPart-TP01']['sections']['Section-S01']['items']['Item-Q03']['maxScore'] = 1.0;
            }
        }

        return $expectedMap;
    }

    private function getExpectedTimeConstraint(string $source, string $qtiClassName = 'assessmentItemRef', string $label = ''): array
    {
        return [
            'allowLateSubmission' => true,
            'label' => $label,
            'maxTime' => false,
            'maxTimeRemaining' => false,
            'minTime' => false,
            'minTimeRemaining' => false,
            'qtiClassName' => $qtiClassName,
            'source' => $source,
            'extraTime' => [
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
            ],
        ];
    }
}
