<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\InitActionProcessor;
use App\TestRunner\ActionProcessor\TimeoutActionProcessor;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class TimeoutActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    /** @var DeliveryExecution */
    private $deliveryExecution;

    /** @var TimeoutActionProcessor */
    private $subject;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $this->subject = new TimeoutActionProcessor(
            static::getContainer()->get(TestContextGenerator::class),
            $deliveryExecutionPropertyService,
            static::getContainer()->get(ItemSessionService::class),
            $this->createMock(TimerService::class),
            $this->createMock(EventDispatcherInterface::class),
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
        );

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Q01/item.json',
            'Q02/item.json',
            'Q03/item.json',
        ], 'BasicTestFullTimerStack');

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicTestFullTimerStack#resultId#tenantId',
            'BasicTestFullTimerStack',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();

        $deliveryExecutionPropertyService->persistTestSession($testSession);

        static::getContainer()->get(InitActionProcessor::class)->process($this->deliveryExecution, ['name' => 'init', 'id' => 'init_1234']);
    }

    public function testItCanGetName(): void
    {
        $this->assertEquals(TimeoutActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testItDoesNotNavigate(): void
    {
        $nextItemIdentifier = $this->processTimeoutAction('Q01');

        $this->assertEquals('Q01', $nextItemIdentifier);
        $this->assertNull($this->deliveryExecution->getFinishedAt());
    }

    public function testItDetectsMultipleSessions(): void
    {
        $this->expectExceptionMessage('Multiple active sessions detected');
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $this->processTimeoutAction('Q02');
    }

    public function testLoggingWorks(): void
    {
        $this->subject->process($this->deliveryExecution, $this->getParameters('Q01'));

        $this->assertHasLogRecordWithMessage(
            'test taker has reached the timeout for ' .
                'item: [Q01] wth ' .
                'itemResponse: [{"RESPONSE":{"base":null}}]',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            "[{$this->deliveryExecution->getId()}] - the following timer has expired for type: qtism\\data\\ExtendedAssessmentItemRef",
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    private function processTimeoutAction(string $itemIdentifier): ?string
    {
        $response = $this->subject->process($this->deliveryExecution, $this->getParameters($itemIdentifier));

        $this->assertTrue($response['success']);

        return $response['values']['testContext']['itemIdentifier'] ?? null;
    }

    private function getParameters(string $itemIdentifier): array
    {
        return [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => [
                'itemIdentifier' => $itemIdentifier,
                'ref' => 1,
                'itemDuration' => 50.09457000000111,
                'itemResponse' => '{"RESPONSE":{"base":null}}',
                'itemState' => '{"RESPONSE":{"response":{"base":null}}}',
                'toolStates' => '',
                'scope' => 'assessmentItemRef',
            ],
        ];
    }
}
