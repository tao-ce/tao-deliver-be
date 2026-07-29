<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\Sender;

use App\DataStore\DTO\AssessmentDataPayloadDTO;
use App\DataStore\DTO\AssessmentResultDTO;
use App\DataStore\DTO\DeliveryDTO;
use App\DataStore\DTO\ItemResultDTO;
use App\DataStore\DTO\ItemsResultsDTO;
use App\DataStore\DTO\OutcomeVariableDTO;
use App\DataStore\DTO\ResponseVariableDTO;
use App\DataStore\DTO\SessionDTO;
use App\DataStore\DTO\TestResultDTO;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DataStoreResultMessage;
use App\Qti\Extractor\ItemResponseStatusResolver;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiFile;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\data\storage\Utils;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\common\OutcomeVariable as QtiOutcomeVariable;
use qtism\runtime\common\ResponseVariable as QtiResponseVariable;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentItemSessionCollection;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DataStoreResultsSender implements DataStoreSenderInterface
{
    public const MAX_SCORE_ID = 'MAXSCORE';
    public const SCORE_ID = 'SCORE';
    public const SCORE_TOTAL_ID = 'SCORE_TOTAL';
    public const SCORE_TOTAL_MAX_ID = 'SCORE_TOTAL_MAX';
    public const SCORE_RATIO = 'SCORE_RATIO';

    private const REQUIRED_TEST_SESSION_OUTCOMES_MAP = [
        self::SCORE_TOTAL_ID => 'totalScore',
        self::SCORE_TOTAL_MAX_ID => 'maxScore',
        self::SCORE_RATIO => 'score',
    ];

    private DeliveryExecution $deliveryExecution;

    public function __construct(
        private FeatureFlagAdapterInterface $featureFlagAdapter,
        private ItemResponseStatusResolver $itemResponseStatusResolver,
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private ExtractDeliveryExecutionResultService $resultExtractor,
        private LoggerInterface $auditPlatformLogger,
        private NormalizerInterface $serializer,
        private MessageBusInterface $messageBus,
        private LtiCustomSettings $ltiCustomSettings,
    ) {
    }

    public function send(DeliveryExecution $deliveryExecution): void
    {
        $this->initializeDeliveryExecution($deliveryExecution);

        $dataStore = new AssessmentDataPayloadDTO(
            $this->getDelivery(),
            $this->getLtiParameters(),
            $this->getAssessmentResult(),
            $deliveryExecution->getUserSelectedLocale(),
            $this->ltiCustomSettings->getSessionData($deliveryExecution->getLtiLaunchParameters()),
        );

        $payload = $this->serializer->normalize($dataStore);

        $this->messageBus->dispatch(
            new DataStoreResultMessage($payload),
        );

        $this->auditPlatformLogger->info(
            sprintf(
                '[%s] The message was successfully sent to Data Store Result Queue.',
                $deliveryExecution->getOriginalId(),
            ),
        );
    }

    private function getDelivery(): DeliveryDTO
    {
        return new DeliveryDTO(
            $this->deliveryExecution->getDeliveryId(),
            $this->deliveryExecution->getDeliveryPublicationTimestamp() ?? -1,
            $this->deliveryExecution->getTenantId(),
        );
    }

    private function getAssessmentResult(): AssessmentResultDTO
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        $itemSessions = $testSession->getAssessmentItemSessionStore()->getAllAssessmentItemSessions();
        $durations = $this->deliveryExecution->getExtraStateData()->getDurationStorage()->getServerDurations();

        $initiallyScoredSession = null;
        $initiallyScoredItemSessions = null;
        if ($this->deliveryExecution->getInitiallyScoredQtiSdkEncodedTestSession()) {
            $initiallyScoredSession = $this->deliveryExecutionPropertyService->fetchTestSession(
                $this->deliveryExecution->getInitiallyScoredDeliveryExecution(),
            );
            $initiallyScoredItemSessions = $initiallyScoredSession
                ->getAssessmentItemSessionStore()
                ->getAllAssessmentItemSessions();
        }

        return new AssessmentResultDTO(
            $this->getTestResults($testSession),
            $initiallyScoredSession ? $this->getTestResults($initiallyScoredSession) : null,
            $this->getSession(),
            $this->getItemsResults($itemSessions, $durations),
            $initiallyScoredItemSessions ? $this->getItemsResults($initiallyScoredItemSessions, $durations) : null,
        );
    }

    private function retrieveDurationFromItemSession(AssessmentTestSession $testSession): int
    {
        $testDuration = $testSession['duration'];
        if (null === $testDuration) {
            return 0;
        }
        return $testDuration->getSeconds(true);
    }

    private function getTestResults(AssessmentTestSession $testSession): TestResultDTO
    {
        $duration = $this->retrieveDurationFromItemSession($testSession);
        $submittedAt = $this->deliveryExecution->getFinishedAt()->getTimestamp();
        $outcomeVariable = $this->getTestOutcomeVariablesByAssessmentSession($testSession);
        return new TestResultDTO($duration, $submittedAt, $outcomeVariable);
    }

    private function getSession(): SessionDTO
    {
        $invalidation = $this->deliveryExecution->getinvalidation();

        return new SessionDTO(
            $this->deliveryExecution->getOriginalId(),
            $this->deliveryExecution->getInitialStartTimestamp(),
            $this->deliveryExecution->getFinishedAt()?->getTimestamp() ?? 0,
            $this->deliveryExecution->getAttempt(),
            $invalidation?->getInvalidatedBy(),
            $invalidation?->getInvalidatedAt()?->getTimestamp(),
            $this->deliveryExecution->isResultInvalidated(),
        );
    }

    private function getLtiParameters(): array
    {
        $ltiParameters = $this->deliveryExecution->getLtiLaunchParameters();
        $payload = [];
        foreach ($ltiParameters as $name => $value) {
            if ($name === 'result_id') {
                $name = 'lis_result_sourcedid';
            }
            $payload[] = [
                'name' => $name,
                'value' => $value,
            ];
        }
        return $payload;
    }

    private function getItemsResults(
        AssessmentItemSessionCollection $itemSessions,
        array $durations,
    ): ItemsResultsDTO {
        $durations = array_reverse($durations);
        $itemPosition = 1;

        $itemsResults = new ItemsResultsDTO();
        $finalManuallyGradedItems = $this->deliveryExecution->getExtraStateData()->getFinalManuallyGradedItems();
        /** @var AssessmentItemSession $itemSession */
        foreach ($itemSessions->getArrayCopy() as $itemSession) {
            $responseVariables = [];
            $outcomeVariables = [];
            $startedTimeStamp = null;
            $submittedTimeStamp = null;

            $id = $itemSession->getAssessmentItem()->getIdentifier();
            /** @var QtiResponseVariable $qtiResponseVariable */
            foreach ($itemSession->getResponseVariables()->getArrayCopy() as $qtiResponseVariable) {
                $isCorrect = $this->checkIsCorrectResponse($qtiResponseVariable);

                $value = $qtiResponseVariable->getValue() ? $qtiResponseVariable->getValue()->__toString() : null;

                $responseVariable = new ResponseVariableDTO(
                    $qtiResponseVariable->getIdentifier(),
                    Cardinality::getNameByConstant($qtiResponseVariable->getCardinality()),
                    $qtiResponseVariable->getBaseType() < 0
                        ? null
                        : BaseType::getNameByConstant($qtiResponseVariable->getBaseType()),
                    $value,
                    $isCorrect,
                    $this->findUploadDocumentIdentifier($qtiResponseVariable),
                );
                $responseVariables[] = $responseVariable;
            }

            /** @var QtiOutcomeVariable $qtiOutcomeVariable */
            foreach ($itemSession->getOutcomeVariables()->getArrayCopy() as $qtiOutcomeVariable) {
                $value = $qtiOutcomeVariable->getValue() ? $qtiOutcomeVariable->getValue()->__toString() : null;
                $outcomeVariable = new OutcomeVariableDTO(
                    $qtiOutcomeVariable->getIdentifier(),
                    Cardinality::getNameByConstant($qtiOutcomeVariable->getCardinality()),
                    $qtiOutcomeVariable->getBaseType() < 0 ? null : BaseType::getNameByConstant($qtiOutcomeVariable->getBaseType()),
                    $value,
                );
                $outcomeVariables[] = $outcomeVariable;
            }

            /** @var ServerDuration $duration */
            foreach ($durations as $key => $duration) {
                if ($duration->getQtiComponentIdentifier() === $id) {
                    unset($durations[$key]);

                    $startedTimeStamp = $duration->getStartedAt();
                    $submittedTimeStamp = $duration->getEndedAt();

                    break;
                }
            }

            $itemResult = new ItemResultDTO(
                $responseVariables,
                $outcomeVariables,
                $startedTimeStamp,
                $submittedTimeStamp,
                $itemPosition,
                true,
                isset($finalManuallyGradedItems[$id]) ? $finalManuallyGradedItems[$id]->getTimestamp() : null,
                $this->createItemState($id),
                $this->itemResponseStatusResolver->isRespondedTo($itemSession, $this->deliveryExecution),
            );

            $itemPosition++;

            $itemsResults->addItemResult($id, $itemResult);
        }

        return $itemsResults;
    }

    private function createItemState(string $itemId): ?array
    {
        static $isItemStatePersistenceEnabled = $this->featureFlagAdapter->isEnabled(
            $this->deliveryExecution->getTenantId(),
            'PERSIST_ITEM_STATE_IN_DATA_STORE',
        );

        if (!$isItemStatePersistenceEnabled) {
            return null;
        }

        $itemState = (string)$this->deliveryExecution->getExtraStateData()->getItemState($itemId);
        return json_decode($itemState, true) ?: null;
    }

    private function checkIsCorrectResponse(QtiResponseVariable $qtiResponseVariable): ?bool
    {
        // If correct response is not set, then it is not possible to determine if the response is correct
        if (!$qtiResponseVariable->hasCorrectResponse()) {
            return null;
        }

        // If the response variable is duration or numAttempts, then it is not possible to determine if the response is correct
        if (in_array($qtiResponseVariable->getIdentifier(), ['duration', 'numAttempts'])) {
            return null;
        }

        return $qtiResponseVariable->isCorrect();
    }

    private function getTestOutcomeVariablesByAssessmentSession(AssessmentTestSession $testSession): array
    {
        $variables = [];
        $requiredVariablesMap = self::REQUIRED_TEST_SESSION_OUTCOMES_MAP;

        foreach ($testSession as $variable) {
            if (!$variable instanceof OutcomeVariable || $variable->getValue() === null) {
                continue;
            }

            $identifier = $variable->getIdentifier();
            $variables[] = new OutcomeVariableDTO(
                $identifier,
                Cardinality::getNameByConstant($variable->getCardinality()),
                BaseType::getNameByConstant($variable->getBaseType()),
                Utils::stringToDatatype((string)$variable->getValue(), $variable->getBaseType()),
            );

            unset($requiredVariablesMap[$identifier]);
        }

        if (!$requiredVariablesMap) {
            return $variables;
        }

        $scores = $this->resultExtractor->extractScores($this->deliveryExecution);

        foreach ($requiredVariablesMap as $outcomeVariableIdentifier => $resultField) {
            $variables[] = new OutcomeVariableDTO(
                $outcomeVariableIdentifier,
                Cardinality::getNameByConstant(Cardinality::SINGLE),
                BaseType::getNameByConstant(BaseType::FLOAT),
                $scores[$resultField],
            );
        }

        return $variables;
    }

    private function findUploadDocumentIdentifier(QtiResponseVariable $qtiResponseVariable): string
    {
        $file = $qtiResponseVariable->getValue();
        if (!$file instanceof QtiFile) {
            return '';
        }

        return $file->getIdentifier();
    }

    private function initializeDeliveryExecution(DeliveryExecution $deliveryExecution): void
    {
        $this->deliveryExecution = $deliveryExecution;
        $this->auditPlatformLogger->info(sprintf('[%s] Delivery was found', $this->deliveryExecution->getDeliveryId()));
    }
}
