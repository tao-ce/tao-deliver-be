<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;
use App\Messenger\Message\DataStoreResultMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use Carbon\Carbon;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\common\datatypes\QtiDuration;
use qtism\common\datatypes\QtiFloat;
use qtism\common\datatypes\QtiInteger;
use qtism\common\datatypes\QtiString;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\data\AssessmentItem;
use qtism\data\AssessmentTest;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\common\RecordContainer;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\common\State;
use qtism\runtime\common\VariableCollection;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionCollection;
use qtism\runtime\tests\AssessmentItemSessionStore;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\DurationStore;
use Symfony\Component\Messenger\MessageBusInterface;

trait DataStoreTestingTrait
{
    use DomainTestingTrait;

    /** @var DataStoreResultMessage */
    private $resultMessage;

    /**
     * @return MockObject
     */
    protected function getTestSessionAccessor(): MockObject
    {
        $itemSessions = [
            $this->createAssessmentItemSessionMock('item1'),
            $this->createAssessmentItemSessionMock('item2'),
        ];

        $dataDuration = [];
        $dataDuration[] = new OutcomeVariable('test', Cardinality::SINGLE, BaseType::DURATION, new QtiDuration('PT5S'));
        $dataDuration[] = new OutcomeVariable('testpart', Cardinality::SINGLE, BaseType::DURATION, new QtiDuration('PT2S'));
        $dataDuration[] = new OutcomeVariable('section', Cardinality::SINGLE, BaseType::DURATION, new QtiDuration('PT3S'));
        $durationStorage = new DurationStore($dataDuration);

        $assessmentTest = $this->createMock(AssessmentTest::class);
        $assessmentTest
            ->method('getIdentifier')
            ->willReturn('test');

        $assessmentItemSessionStore = $this->createMock(AssessmentItemSessionStore::class);
        $assessmentItemSessionStore->method('getAllAssessmentItemSessions')->willReturn(new AssessmentItemSessionCollection($itemSessions));
        /** @var AssessmentTestSession $assessmentTestSession */
        $assessmentTestSession = $this->getMockBuilder(AssessmentTestSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAssessmentItemSessionStore', 'getDurationStore', 'getAssessmentTest'])
            ->getMock();
        $assessmentTestSession->method('getAssessmentItemSessionStore')->willReturn($assessmentItemSessionStore);
        $assessmentTestSession->method('getDurationStore')->willReturn($durationStorage);
        $assessmentTestSession->method('getAssessmentTest')->willReturn($assessmentTest);

        foreach ($this->getOutcomeVariables() as $outcomeVariable) {
            $assessmentTestSession->setVariable(
                new OutcomeVariable(
                    $outcomeVariable['identifier'],
                    Cardinality::getConstantByName($outcomeVariable['cardinality']),
                    BaseType::getConstantByName($outcomeVariable['baseType']),
                    new QtiFloat($outcomeVariable['value']),
                ),
            );
        }
        $testSessionAccessor = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessor->method('retrieve')->willReturn($assessmentTestSession);

        return $testSessionAccessor;
    }

    /**
     * @return MockObject
     */
    protected function getDeliveryRepositoryMock(): MockObject
    {
        $deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $delivery = $this->createMock(Delivery::class);
        $delivery
            ->method('getId')
            ->willReturn('1');
        $delivery
            ->method('getTenantId')
            ->willReturn('1');
        $delivery
            ->method('getCreatedAt')
            ->willReturn(Carbon::now());
        $deliveryRepositoryMock
            ->method('find')
            ->willReturn($delivery);

        return $deliveryRepositoryMock;
    }

    protected function getTestSessionAccessorFactoryMock(): TestSessionAccessorFactory&MockObject
    {
        $testSessionAccessorFactory = $this->createMock(TestSessionAccessorFactory::class);
        $testSessionAccessorFactory->method('create')->willReturn($this->getTestSessionAccessor());

        return $testSessionAccessorFactory;
    }

    /**
     * @return MockObject
     */
    protected function getMessageBusMock(): MessageBusInterface&MockObject
    {
        return $this->createMock(MessageBusInterface::class);
    }

    protected function getPostProcessMessageBusMock(): PostProcessedMessageBusInterface&MockObject
    {
        return $this->createMock(PostProcessedMessageBusInterface::class);
    }

    /**
     * @return DataStoreResultMessage
     */
    protected function getResultMessage(): DataStoreResultMessage
    {
        if ($this->resultMessage === null) {
            $this->resultMessage = new DataStoreResultMessage($this->getPayloadArray());
        }

        return $this->resultMessage;
    }

    /**
     * @return DataStoreResultMessage
     */
    protected function getResultMessageWithManualScored(string $itemId): DataStoreResultMessage
    {
        $resultPayload = $this->getPayloadArray();
        $resultPayload['assessmentResult']['itemResults'][$itemId][0]['manuallyGradedAt'] = Carbon::now()->timestamp;

        return new DataStoreResultMessage($resultPayload);
    }

    /**
     * @return ResponseVariable[]
     */
    protected function generateResponseVariables(): array
    {
        $responseVariable = new ResponseVariable(
            'resultId',
            Cardinality::SINGLE,
            BaseType::STRING,
            new QtiString('test'),
        );
        $responseVariable->setCorrectResponse(new QtiString('test'));

        $responseRecordVariable = new ResponseVariable(
            'recordResultId',
            Cardinality::RECORD,
            -1,
            new RecordContainer(['r1' => new QtiString('test')]),
        );

        $responseNumAttemptsVariable = new ResponseVariable(
            'numAttempts',
            Cardinality::SINGLE,
            BaseType::INTEGER,
            new QtiInteger(1),
        );

        return [
            $responseVariable,
            $responseRecordVariable,
            $responseNumAttemptsVariable,
        ];
    }

    /**
     * @param string $id
     * @param array $ltiParameters
     * @return DeliveryExecution
     */
    protected function getDeliveryExecution(array $ltiParameters): DeliveryExecution
    {
        return $this
            ->createTestDeliveryExecution(
                'userId#deliveryId#resultId#tenantId',
                'deliveryId',
                'tenantId',
                $ltiParameters,
                'testSession',
                DeliveryExecutionExtraStateData::fromArray([
                    'durationStorage' => new DurationStorage(
                        serverDurations: [
                            new ServerDuration('item1', Carbon::now()->timestamp, Carbon::now()->timestamp + 7),
                            new ServerDuration('item1', Carbon::now()->timestamp, Carbon::now()->timestamp),
                            new ServerDuration('item2', Carbon::now()->timestamp, Carbon::now()->timestamp + 5),
                        ],
                    ),
                    'deliveryPublicationTime' => Carbon::now()->timestamp,
                ]),
                DeliveryExecution::STATUS_INITIAL,
                Carbon::now(),
                Carbon::now(),
                locale: 'en-US',
            )->addItemState('item1', json_encode(['item' => 'state']))
            ->addItemState('item2', '{invalid-state}')
            ->close();
    }

    /**
     * @return array
     */
    protected function getPayloadArray(): array
    {
        $timestamp = Carbon::now()->getTimestamp();
        $deliveryExecution = $this->getDeliveryExecution(
            $this->getLtiParameters(),
        );

        return [
            'delivery' => [
                'tenantId' => 'tenantId',
                'publicationTime' => $timestamp,
                'identifier' => 'deliveryId',
            ],
            'ltiParameters' => $this->normalizeLtiLaunchParameters(
                $deliveryExecution->getLtiLaunchParameters(),
            ),
            'assessmentResult' => [
                'testResult' => [
                    'duration' => 5,
                    'submittedAt' => $timestamp,
                    'outcomeVariable' => $this->getOutcomeVariables(),
                ],
                'previousTestResult' => null,
                'session' => [
                    'sessionId' => 'userId#deliveryId#resultId#tenantId',
                    'startTimeStamp' => $timestamp,
                    'endTimeStamp' => $timestamp,
                    'attempt' => $deliveryExecution->getAttempt(),
                    'invalidatedBy' => null,
                    'invalidatedAt' => null,
                    'isResultInvalidated' => false,
                ],
                'itemResults' => [
                    'item1' => [
                        [
                            'responseVariable' => [
                                [
                                    'identifier' => 'resultId',
                                    'cardinality' => 'single',
                                    'baseType' => 'string',
                                    'value' => 'test',
                                    'correct' => true,
                                    'uploadDocumentIdentifier' => '',
                                ],
                                [
                                    'identifier' => 'recordResultId',
                                    'cardinality' => 'record',
                                    'baseType' => null,
                                    'value' => '{\'test\'}',
                                    'correct' => null,
                                    'uploadDocumentIdentifier' => '',
                                ],
                                [
                                    'identifier' => 'numAttempts',
                                    'cardinality' => 'single',
                                    'baseType' => 'integer',
                                    'value' => '1',
                                    'correct' => null,
                                    'uploadDocumentIdentifier' => '',
                                ],
                            ],
                            'outcomeVariable' => [
                                [
                                    'identifier' => 'resultId',
                                    'cardinality' => 'single',
                                    'baseType' => 'string',
                                    'value' => 'test',
                                ],
                                [
                                    'identifier' => 'recordResultId',
                                    'cardinality' => 'record',
                                    'baseType' => null,
                                    'value' => '{\'test\'}',
                                ],
                                [
                                    'identifier' => 'numAttempts',
                                    'cardinality' => 'single',
                                    'baseType' => 'integer',
                                    'value' => '1',
                                ],
                            ],
                            'startedTimeStamp' => Carbon::now()->timestamp,
                            'submittedTimeStamp' => Carbon::now()->timestamp,
                            'itemPosition' => 1,
                            'lastAttempt' => true,
                            'interaction' => [],
                            'customVariable' => [],
                            'manuallyGradedAt' => null,
                            'state' => ['item' => 'state'],
                            'answered' => false,
                        ],
                    ],
                    'item2' => [
                        [
                            'responseVariable' => [
                                [
                                    'identifier' => 'resultId',
                                    'cardinality' => 'single',
                                    'baseType' => 'string',
                                    'value' => 'test',
                                    'correct' => true,
                                    'uploadDocumentIdentifier' => '',
                                ],
                                [
                                    'identifier' => 'recordResultId',
                                    'cardinality' => 'record',
                                    'baseType' => null,
                                    'value' => '{\'test\'}',
                                    'correct' => null,
                                    'uploadDocumentIdentifier' => '',
                                ],
                                [
                                    'identifier' => 'numAttempts',
                                    'cardinality' => 'single',
                                    'baseType' => 'integer',
                                    'value' => '1',
                                    'correct' => null,
                                    'uploadDocumentIdentifier' => '',
                                ],
                            ],
                            'outcomeVariable' => [
                                [
                                    'identifier' => 'resultId',
                                    'cardinality' => 'single',
                                    'baseType' => 'string',
                                    'value' => 'test',
                                ],
                                [
                                    'identifier' => 'recordResultId',
                                    'cardinality' => 'record',
                                    'baseType' => null,
                                    'value' => '{\'test\'}',
                                ],
                                [
                                    'identifier' => 'numAttempts',
                                    'cardinality' => 'single',
                                    'baseType' => 'integer',
                                    'value' => '1',
                                ],
                            ],
                            'startedTimeStamp' => Carbon::now()->timestamp,
                            'submittedTimeStamp' => Carbon::now()->timestamp + 5,
                            'itemPosition' => 2,
                            'lastAttempt' => true,
                            'interaction' => [],
                            'customVariable' => [],
                            'manuallyGradedAt' => null,
                            'state' => null,
                            'answered' => false,
                        ],
                    ],
                ],
                'previousItemResults' => null,
            ],
            'locale' => 'en-US',
            'sessionData' => [],
        ];
    }

    /**
     * @return array
     */
    protected function getLtiParameters(): array
    {
        return [
            'user_id' => 'user_id',
            'context_id' => 'context_id',
            'lti_version' => 'lti_version',
            'roles' => ['roles'],
            'result_id' => 'lis_result_sourcedid',
            'lis_outcome_service_url' => 'lis_outcome_service_url',
            'lis_person_name_full' => 'lis_person_name_full',
            'user_name' => 'user_name',
            'user_locale' => 'user_locale',
            'launch_presentation_locale' => 'launch_presentation_locale',
            'resource_link_id' => 'resource_link_id',
            'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
                'scope' => [
                    "https://purl.imsglobal.org/spec/lti-ags/scope/lineitem",
                    "https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly",
                    "https://purl.imsglobal.org/spec/lti-ags/scope/score",
                ],
                "lineitems" => "https://lti-udir.dev.gcp.taocloud.org/platform/service/ags/default/lineitems",
                "lineitem" => "https://lti-udir.dev.gcp.taocloud.org/platform/service/ags/1/lineitems/test",
            ],
        ];
    }

    private function getOutcomeVariables(): array
    {
        return [
            [
                'identifier' => 'SCORE_TOTAL',
                'cardinality' => 'single',
                'baseType' => 'float',
                'value' => .0,
            ],
            [
                'identifier' => 'SCORE_TOTAL_MAX',
                'cardinality' => 'single',
                'baseType' => 'float',
                'value' => .0,
            ],
            [
                'identifier' => 'SCORE_RATIO',
                'cardinality' => 'single',
                'baseType' => 'float',
                'value' => .0,
            ],
        ];
    }

    private function normalizeLtiLaunchParameter(string $parameter): string
    {
        static $ltiParametersMap = [
            'result_id' => 'lis_result_sourcedid',
        ];

        return $ltiParametersMap[$parameter] ?? $parameter;
    }

    private function normalizeLtiLaunchParameters(array $parameters): array
    {
        $normalizedParameters = [];

        foreach ($parameters as $parameter => $parameterValue) {
            $normalizedParameters[] = [
                'name' => $this->normalizeLtiLaunchParameter($parameter),
                'value' => $parameterValue,
            ];
        }

        return $normalizedParameters;
    }

    private function createExtractDeliveryExecutionResultServiceMock(): ExtractDeliveryExecutionResultService|MockObject
    {
        $resultExtractor = $this->createMock(ExtractDeliveryExecutionResultService::class);

        $resultExtractor
            ->method('extract')
            ->willReturn([
                'score' => .0,
                'maxScore' => .0,
                'totalScore' => .0,
                'deliveryExecutionId' => 'userId#deliveryId#resultId#tenantId',
                'qtiModels' => [],
            ]);

        return $resultExtractor;
    }

    private function createAssessmentItemMock(string $identifier): AssessmentItem
    {
        $assessmentItemMock = $this->createMock(AssessmentItem::class);
        $assessmentItemMock->method('getIdentifier')->willReturn($identifier);

        return $assessmentItemMock;
    }

    private function createAssessmentItemSessionMock(string $identifier): AssessmentItemSession
    {
        $itemSessionMock = $this->createMock(AssessmentItemSession::class);
        $itemSessionMock->method('getResponseVariables')->willReturn(new State($this->generateResponseVariables()));
        $itemSessionMock->method('getOutcomeVariables')->willReturn(new State($this->generateResponseVariables()));
        $itemSessionMock->method('getAllVariables')->willReturn(new VariableCollection($this->generateResponseVariables()));
        $itemSessionMock->method('getAssessmentItem')->willReturn(
            $this->createAssessmentItemMock($identifier),
        );

        return $itemSessionMock;
    }
}
