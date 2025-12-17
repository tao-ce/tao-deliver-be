<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\InitActionProcessor;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Monolog\Logger;
use qtism\runtime\tests\AssessmentTestSessionState;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InitActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    /** @var InitActionProcessor */
    private $subject;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->subject = static::getContainer()->get(InitActionProcessor::class);
    }

    public function testItImplementsActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testGetNameReturnTheExpectedActionName(): void
    {
        $this->assertEquals('init', InitActionProcessor::ACTION_NAME);
        $this->assertEquals(InitActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testInitActionSucceed(): void
    {
        $actionsParameters = ['name' => 'init', 'id' => 'init_1234', 'timestamp' => '1234', 'parameters' => []];
        $expectedResponse = $this->getExpectedInitActionResponse();
        $response = $this->subject->process(
            $this->getDeliveryExecutionWithStartedTestSession(
                DeliveryExecution::STATUS_INTERACTING,
                AssessmentTestSessionState::INTERACTING,
            ),
            $actionsParameters,
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker has initialized the test: Test-T01',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertEquals($expectedResponse, $response);
    }

    public function testItFailsIfTestSessionIsWithWrongStatus(): void
    {
        $actionsParameters = [
            'name' => 'init',
            'id' => 'init_1234',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Init action can not be started, the test session or delivery execution current status is not as expected');

        $this->subject->process(
            $this->getDeliveryExecutionWithStartedTestSession(
                DeliveryExecution::STATUS_INTERACTING,
                AssessmentTestSessionState::INITIAL,
            ),
            $actionsParameters,
        );
    }

    public function testInitActionSucceedWithSuspendedStatus(): void
    {
        $actionsParameters = [
            'name' => 'init',
            'id' => 'init_1234',
            'timestamp' => '1234',
            'parameters' => [],
        ];

        $response = $this->subject->process(
            $this->getDeliveryExecutionWithStartedTestSession(
                DeliveryExecution::STATUS_SUSPENDED,
                AssessmentTestSessionState::SUSPENDED,
            ),
            $actionsParameters,
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker has initialized the test: Test-T01',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertEquals($this->getExpectedInitActionResponse(true), $response);
    }

    private function getExpectedInitActionResponse(bool $isSuspended = false): array
    {
        return [
            'success' => true,
            'name' => 'init',
            'id' => 'init_1234',
            'errorCode' => null,
            'errorMessage' => null,
            'values' => [
                'testContext' => $this->getExpectedTestContext($isSuspended),
                'testMap' => $this->getExpectedTestMap(),
                'timer' => null,
                'baseUrl' => '',
                'itemIdentifier' => 'Item-Q01',
                'itemState' => null,
            ],
        ];
    }

    private function getExpectedTestContext(bool $isSuspended = false): array
    {
        return [
            'state' => AssessmentTestSessionState::INTERACTING,
            'remainingAttempts' => -1,
            'isTimeout' => 0,
            'itemIdentifier' => 'Item-Q01',
            'attempt' => 1,
            'itemSessionState' => $isSuspended ? AssessmentTestSessionState::SUSPENDED : AssessmentTestSessionState::INTERACTING,
            'needMapUpdate' => false,
            'itemPosition' => 0,
            'timeConstraints' => [],
            'testPartId' => 'TestPart-TP01',
            'sectionId' => 'Section-S01',
            'canMoveBackward' => false,
            'rubrics' => '',
            'allowSkipping' => true,
            'validateResponses' => false,
            'status' => $isSuspended ? DeliveryExecution::STATUS_SUSPENDED : DeliveryExecution::STATUS_INTERACTING,
            'isProctored' => false,
        ];
    }

    private function getExpectedTestMap(): array
    {
        return [
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
                    'isLinear' => true,
                    'isIndividual' => true,
                    'allowSkipping' => true,
                    'validateResponses' => false,
                    'maxAttempts' => 0,
                    'timeConstraint' => $this->getExpectedTimeConstraint('TestPart-TP01', 'testPart', 'TestPart-TP01'),
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
                            'timeConstraint' => $this->getExpectedTimeConstraint('Section-S01', 'assessmentSection', 'Section 01'),
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
                                    'categories' => [],
                                    'hasFeedbacks' => false,
                                    'allowComment' => false,
                                    'timeConstraint' => $this->getExpectedTimeConstraint('Item-Q01'),
                                    'informational' => false,
                                    'externalScored' => false,
                                    'hasItemState' => false,
                                    'attachments' => [],
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
                                    'categories' => [],
                                    'hasFeedbacks' => false,
                                    'allowComment' => false,
                                    'timeConstraint' => $this->getExpectedTimeConstraint('Item-Q02'),
                                    'informational' => false,
                                    'externalScored' => false,
                                    'hasItemState' => false,
                                    'attachments' => [],
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
                                    'categories' => [],
                                    'hasFeedbacks' => false,
                                    'allowComment' => false,
                                    'timeConstraint' => $this->getExpectedTimeConstraint('Item-Q03'),
                                    'informational' => false,
                                    'externalScored' => false,
                                    'hasItemState' => false,
                                    'attachments' => [],
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

    private function getDeliveryExecutionWithStartedTestSession(
        string $deliveryExecutionStatus,
        int $sessionStatus,
    ): DeliveryExecution {
        $this->copyCompiledTestToStorage();

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            deliveryId: 'Basic',
            testSession: '',
            extraStateData: new DeliveryExecutionExtraStateData(),
            status: $deliveryExecutionStatus,
        );

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $testSession = $deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($sessionStatus !== AssessmentTestSessionState::INITIAL) {
            $testSession->beginTestSession();
            $testSession->beginAttempt();
            switch ($sessionStatus) {
                case AssessmentTestSessionState::SUSPENDED:
                    $testSession->suspend();
                    break;
                case AssessmentTestSessionState::CLOSED:
                    $testSession->endTestSession();
                    break;
            }
        }

        $deliveryExecutionPropertyService->persistTestSession($testSession);

        return $deliveryExecution;
    }
}
