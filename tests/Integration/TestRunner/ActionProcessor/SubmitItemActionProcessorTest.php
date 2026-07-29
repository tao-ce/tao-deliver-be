<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\SubmitItemActionProcessor;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;

class SubmitItemActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private SubmitItemActionProcessor $subject;

    public function setUp(): void
    {
        static::bootKernel();
        $this->setUpTestLogHandler();

        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->subject = static::getContainer()->get(SubmitItemActionProcessor::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals(SubmitItemActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testProcess(): void
    {
        $actionsParameters = [
            'name' => SubmitItemActionProcessor::ACTION_NAME,
            'id' => SubmitItemActionProcessor::ACTION_NAME . '_1234',
            'timestamp' => '1234',
            'parameters' => [
                'itemIdentifier' => 'Item-Q01',
                'itemDuration' => '12345',
                'itemResponse' => '{"RESPONSE":{"base":{"identifier":"tao"}}}',
                'itemState' => 'someItemState',
                'scope' => 'someScope',
            ],
        ];
        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(DeliveryExecution::STATUS_INTERACTING);
        $response = $this->subject->process(
            $deliveryExecution,
            $actionsParameters,
        );

        $this->assertHasLogRecordWithMessage(
            "[{$deliveryExecution->getId()}]"
            . ' - test taker has submitted the following item: [Item-Q01] with ItemResponse: '
            . '[{"RESPONSE":{"base":{"identifier":"tao"}}}] and itemState: [someItemState] ',
            Level::Info,
            'audit_delivery_execution',
        );

        $variableElements = file_get_contents(__DIR__ . '/../../../Resources/Qti/CompiledPackages/Basic/Item-Q01/variableElements.json');
        $expectedFeedbacks = json_decode($variableElements, true);

        $expectedResponse = [
            'success' => true,
            'name' => SubmitItemActionProcessor::ACTION_NAME,
            'id' => SubmitItemActionProcessor::ACTION_NAME . '_1234',
            'errorCode' => null,
            'errorMessage' => null,
            'values' => [
                'displayFeedbacks' => true,
                'feedbacks' => $expectedFeedbacks,
                'itemSession' => [
                    'numAttempts' => ['base' => ['integer' => 1]],
                    'duration' => ['base' => ['duration' => 'PT3H25M45S']],
                    'completionStatus' => ['base' => ['identifier' => 'completed']],
                    'SCORE' => ['base' => ['float' => 1.0]],
                    'RESPONSE' => ['base' => ['identifier' => 'tao']],
                    'itemAnswered' => true,
                    'MAXSCORE' => ['base' => ['float' => 1.0]],
                ],
            ],
        ];

        $this->assertEquals($expectedResponse, $response);
    }

    public function testItDetectsMultipleSessions(): void
    {
        $this->expectExceptionMessage('Multiple active sessions detected');
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $actionsParameters = [
            'name' => SubmitItemActionProcessor::ACTION_NAME,
            'id' => SubmitItemActionProcessor::ACTION_NAME . '_1234',
            'timestamp' => '1234',
            'parameters' => [
                'itemIdentifier' => 'Item-Q02',
                'itemDuration' => '12345',
                'itemResponse' => '{"RESPONSE":{"base":{"identifier":"tao"}}}',
                'itemState' => 'someItemState',
                'scope' => 'someScope',
            ],
        ];

        $this->subject->process(
            $this->getDeliveryExecutionWithStartedTestSession(DeliveryExecution::STATUS_INTERACTING),
            $actionsParameters,
        );
    }

    private function getDeliveryExecutionWithStartedTestSession(
        string $deliveryExecutionStatus,
        bool $sessionStarted = true,
    ): DeliveryExecution {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/variableElements.json',
        ]);

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['ltiLaunchParams'],
            null,
            new DeliveryExecutionExtraStateData(),
            $deliveryExecutionStatus,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($sessionStarted) {
            $testSession->beginTestSession();
            $testSession->beginAttempt();
        }

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        return $deliveryExecution;
    }
}
