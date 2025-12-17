<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Messenger\Message\DeliveryExecutionItemExternalScoringMessage;
use App\Qti\Service\AssessmentResultUpdateService;
use App\Qti\Service\Contract\ArgumentAssessmentResultInterface;
use App\Qti\Service\Contract\ArgumentOutcomeVariableInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use DateTimeInterface;
use JsonException;
use Monolog\Logger;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AssessmentResultUpdateServiceTest extends KernelTestCase
{
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private AssessmentResultUpdateService $subject;
    private DeliveryExecution $deliveryExecution;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->createTestSession();
        $this->subject = static::getContainer()->get(AssessmentResultUpdateService::class);
    }

    public function testSessionUpdateCorrectly(): void
    {
        $input = $this->getInputArgument();
        $originalTestSession = $this->deliveryExecution->getQtiSdkEncodedTestSession();
        $testSession = $this->subject->updateOutcomeVariableOnAssessmentSession(
            $this->deliveryExecution,
            $input,
        );

        foreach ($input->getTestResult()->outcomeVariableList as $testOutcome) {
            $this->assertSessionVariableEqualToOutcomeVariable($testSession, $testOutcome);
        }

        foreach ($input->getItemResultAssocList() as $itemId => $itemResult) {
            /** @var AssessmentItemSession $itemSession */
            $itemSession = $testSession->getAssessmentItemSessions($itemId)->current();
            foreach ($itemResult->getOutcomeVariableAssoc() as $itemOutcome) {
                $this->assertSessionVariableEqualToOutcomeVariable($itemSession, $itemOutcome);
            }
        }

        $this->assertSame(
            $originalTestSession,
            $this->deliveryExecution->getExtraStateData()->getOriginalSession(),
        );
    }

    /**
     * @throws JsonException
     */
    private function getInputArgument(): ArgumentAssessmentResultInterface
    {
        return DeliveryExecutionItemExternalScoringMessage::fromArray($this->getInputMessage());
    }

    private function getInputMessage(): array
    {
        $payload = file_get_contents(
            __DIR__ . '/../../../Resources/Payload/DeliveryExecutionItemExternalScoringMessage.json',
        );

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    private function createTestSession(string $packageName = 'BasicWithExternalScored'): void
    {
        $this->deliveryExecution = $this->createTestDeliveryExecution(
            "userId#$packageName#resultId#tenantId",
            $packageName,
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q02/item.json',
            'Item-Q03/item.json',
        ], $packageName);

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $testSession = $deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);

        $testSession->beginTestSession();
        $testSession->beginAttempt();

        $deliveryExecutionPropertyService->persistTestSession($testSession);
    }

    protected function createTestDeliveryExecution(
        string $id = 'userId#deliveryId#resultId#tenantId',
        string $deliveryId = 'deliveryId',
        string $tenantId = 'tenantId',
        array $ltiLaunchParameters = ['ltiLaunchParams'],
        ?string $testSession = 'testSession',
        ?DeliveryExecutionExtraStateData $extraStateData = null,
        string $status = DeliveryExecution::STATUS_INITIAL,
        ?DateTimeInterface $startedAt = null,
        ?DateTimeInterface $finishedAt = null,
        ?DateTimeInterface $closeAt = null,
    ): DeliveryExecution {
        if (!isset($ltiLaunchParameters['result_id'])) {
            $ltiLaunchParameters['result_id'] = 'test_taker_result_id';
        }

        return new DeliveryExecution(
            $id,
            $deliveryId,
            $tenantId,
            $startedAt ?? Carbon::now(),
            $ltiLaunchParameters,
            $testSession,
            $extraStateData ?? new DeliveryExecutionExtraStateData(),
            $status,
            $finishedAt,
            $closeAt,
        );
    }

    private function assertSessionVariableEqualToOutcomeVariable(
        AssessmentTestSession|AssessmentItemSession $session,
        ArgumentOutcomeVariableInterface $inputVariable,
    ): void {
        if (!$inputVariable->isApplicable()) {
            return;
        }
        $variable = $session->getVariable($inputVariable->getId());
        $this->assertSame(
            $inputVariable->getBaseType(),
            BaseType::getNameByConstant($variable->getBaseType()),
        );
        $this->assertSame(
            $inputVariable->getCardinality(),
            Cardinality::getNameByConstant($variable->getCardinality()),
        );
        $this->assertEquals(
            $inputVariable->getValue(),
            $variable->getValue(),
        );
    }
}
