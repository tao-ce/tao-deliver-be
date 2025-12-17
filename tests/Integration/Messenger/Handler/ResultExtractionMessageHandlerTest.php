<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Messenger\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\Sender\LtiAgsScoreSender;
use App\Lti\Sender\LtiResultSenderInterface;
use App\Messenger\Handler\ResultExtractionMessageHandler;
use App\Messenger\Message\ResultExtractionMessage;
use App\Registry\LtiResultSenderRegistry;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use App\Service\DeliveryExecution\LoggerAwareDeliveryExecutionService;
use App\Service\DeliveryExecution\ScoringEligibilityChecker;
use App\Service\Lti\LtiAgsScoreService;
use App\TestRunner\Service\DeliveryExecutionClosureService;
use App\TestRunner\Service\TestSessionInitiator;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use DateTimeInterface;
use League\Flysystem\FilesystemReader;
use OAT\Library\EnvironmentManagementLtiClient\Client\LtiAgsClient;
use OAT\Library\EnvironmentManagementLtiClient\Exception\LtiAgsClientException;
use OAT\Library\Lti1p3Ags\Factory\Score\ScoreFactory;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\Key;
use OAT\Library\Lti1p3Core\Security\Key\KeyChain;
use OAT\Library\Lti1p3Core\Tool\Tool;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ResultExtractionMessageHandlerTest extends KernelTestCase
{
    use LoggerTestingTrait;
    use DocumentTestingTrait;
    use MessengerTestingTrait;
    use DomainTestingTrait;
    use QtiTestingTrait;

    private FilesystemReader $resultStorage;
    private ResultExtractionMessageHandler $subject;
    private RegistrationRepositoryInterface $registrationRepositoryMock;
    private LtiAgsClient $ltiAgsClientMock;
    private LtiResultSenderInterface $resultSenderMock;

    public function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestDocumentManager();
        $this->setUpTestMessageBus();

        $this->resultSenderMock = $this->createMock(LtiResultSenderInterface::class);
        $this->resultStorage = static::getContainer()->get('delivery_execution_result.storage');

        $this->registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $this->ltiAgsClientMock = $this->createMock(LtiAgsClient::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function testItHandlesAndSendBasicOutputCallWithSuccessResponse(): void
    {
        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));
        $delivery = $this->createTestDelivery(id: 'Basic', compactTestFilePath: 'Basic/compact-test.xml');
        $this->saveDocument($delivery);

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INTERACTING,
            Carbon::now(),
            Carbon::now(),
        );
        $this->saveDocument($deliveryExecution);

        $this->subject = $this->createSubjectForBasicOutcomeSend();

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId());

        $this->subject->__invoke($message);

        $resultPath = "{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}";
        $this->assertTrue($this->resultStorage->has($this->normalizeResultId($resultPath)));

        $resultFileContent = $this->resultStorage->read($this->normalizeResultId($resultPath));

        $this->assertNotEmpty($resultFileContent);
    }

    public function testItHandlesAndSendAgsCallWithSuccessResponse(): void
    {
        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        $delivery = $this->createTestDelivery('Basic');
        $this->saveDocument($delivery);
        $this->mockRegistrationRepository();

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INTERACTING,
            Carbon::now(),
            Carbon::now(),
            [
                'platform_issuer' => 'platformAudience',
                'client_id' => 'registrationClientId',
                'user_id' => 'userId',
                'context_id' => 'contextId',
                'result_id' => 'test_taker_id',
                'ags_claim' => [
                    'scope' => ['https://purl.imsglobal.org/spec/lti-ags/scope/score'],
                    'lineitems' => 'https://www.test.com/contextId/lineitems',
                    'lineitem' => 'https://www.test.com/contextId/lineitems/1',
                ],
            ],
        );

        $this->saveDocument($deliveryExecution);

        $this->subject = $this->createSubjectForAgsSend();

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId());

        $this->subject->__invoke($message);

        $resultLocation = "{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}";
        $this->assertTrue($this->resultStorage->has($this->normalizeResultId($resultLocation)));

        $resultFileContent = $this->resultStorage->read($this->normalizeResultId($resultLocation));
        $this->assertNotEmpty($resultFileContent);
    }

    public function testItHandlesForceClosureWhenTestIsNotStarted(): void
    {
        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));
        $delivery = $this->createTestDelivery(id: 'Basic', compactTestFilePath: 'Basic/compact-test.xml');
        $this->saveDocument($delivery);

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INITIAL,
            Carbon::now(),
        );
        $this->saveDocument($deliveryExecution);

        $this->subject = $this->createSubjectForBasicOutcomeSend();

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId(), true);

        $this->subject->__invoke($message);

        $resultPath = "{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}";
        $this->assertFalse($this->resultStorage->has($this->normalizeResultId($resultPath)));

        $this->subject->__invoke(new ResultExtractionMessage('1234', $deliveryExecution->getId()));
        $resultFileContent = $this->resultStorage->read($this->normalizeResultId($resultPath));

        $this->assertNotEmpty($resultFileContent);
    }

    public function testItSkipsHandlingWhenTestIsNotStarted(): void
    {
        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));
        $delivery = $this->createTestDelivery(id: 'Basic', compactTestFilePath: 'Basic/compact-test.xml');
        $this->saveDocument($delivery);

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INITIAL,
            Carbon::now(),
        );
        $this->saveDocument($deliveryExecution);

        $this->subject = $this->createSubjectForBasicOutcomeSend();

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId());

        $this->subject->__invoke($message);

        $resultPath = "{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}";
        $this->assertFalse($this->resultStorage->has($this->normalizeResultId($resultPath)));
    }

    public function testItGracefullyStopsWhenTestIsNotFinished(): void
    {
        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        $delivery = $this->createTestDelivery('Basic');
        $this->saveDocument($delivery);

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INTERACTING,
            Carbon::now(),
            null,
            [
                'platform_issuer' => 'platformAudience2',
                'client_id' => 'registrationClientId',
                'user_id' => 'userId',
                'context_id' => 'contextId',
                'result_id' => 'test_taker_id',
                'ags_claim' => [
                    'scope' => ['https://purl.imsglobal.org/spec/lti-ags/scope/score'],
                    'lineitems' => 'https://www.test.com/contextId/lineitems',
                    'lineitem' => 'https://www.test.com/contextId/lineitems/1',
                ],
            ],
        );

        $this->saveDocument($deliveryExecution);
        $this->resultSenderMock->expects($this->never())->method('send');

        $this->subject = $this->createBasicSubject();

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId());
        $this->subject->__invoke($message);
    }

    public function testItThrowsExceptionWhenRegistrationNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to get registration id associated to LTI launch');

        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        $delivery = $this->createTestDelivery('Basic');
        $this->saveDocument($delivery);
        $this->mockRegistrationRepositoryWithEmpty();

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INTERACTING,
            Carbon::now(),
            Carbon::now(),
            [
                'platform_issuer' => 'platformAudience2',
                'client_id' => 'registrationClientId',
                'user_id' => 'userId',
                'context_id' => 'contextId',
                'result_id' => 'test_taker_id',
                'ags_claim' => [
                    'scope' => ['https://purl.imsglobal.org/spec/lti-ags/scope/score'],
                    'lineitems' => 'https://www.test.com/contextId/lineitems',
                    'lineitem' => 'https://www.test.com/contextId/lineitems/1',
                ],
            ],
        );

        $this->saveDocument($deliveryExecution);

        $this->subject = $this->createSubjectForAgsSend();

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId());

        $this->subject->__invoke($message);
    }

    public function testItThrowsExceptionOnAgsFailResponse(): void
    {
        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        $delivery = $this->createTestDelivery('Basic');
        $this->saveDocument($delivery);
        $this->mockRegistrationRepository();

        $deliveryExecution = $this->getDeliveryExecutionWithStartedTestSession(
            DeliveryExecution::STATUS_INTERACTING,
            Carbon::now(),
            Carbon::now(),
            [
                'platform_issuer' => 'platformAudience',
                'client_id' => 'registrationClientId',
                'user_id' => 'userId',
                'context_id' => 'contextId',
                'result_id' => 'test_taker_id',
                'ags_claim' => [
                    'scope' => ['https://purl.imsglobal.org/spec/lti-ags/scope/score'],
                    'lineitems' => 'https://www.test.com/contextId/lineitems',
                    'lineitem' => 'https://www.test.com/contextId/lineitems/1',
                ],
            ],
        );

        $this->expectException(LtiAgsClientException::class);
        $this->expectExceptionMessage('LTI AGS score publication failed');

        $this->saveDocument($deliveryExecution);

        $this->subject = $this->createSubjectForAgsSend();

        $this->ltiAgsClientMock
            ->method('publishScore')
            ->willThrowException(new LtiAgsClientException('LTI AGS score publication failed'));

        $message = new ResultExtractionMessage('1234', $deliveryExecution->getId());

        $this->subject->__invoke($message);
    }

    private function createSubjectForBasicOutcomeSend(): ResultExtractionMessageHandler
    {
        $ltiResultSenderRegistry = new LtiResultSenderRegistry(
            [
                LtiMessageInterface::LTI_VERSION => $this->createLtiAgsSender(),
            ],
        );

        return $this->createResultExtractionMessageHandler($ltiResultSenderRegistry);
    }

    private function createBasicSubject(): ResultExtractionMessageHandler
    {
        $ltiResultSenderRegistry = new LtiResultSenderRegistry([$this->resultSenderMock]);

        return new ResultExtractionMessageHandler(
            static::getContainer()->get(LoggerAwareDeliveryExecutionService::class),
            static::getContainer()->get(ExtractDeliveryExecutionResultService::class),
            static::getContainer()->get(DeliveryExecutionClosureService::class),
            static::getContainer()->get(LoggerInterface::class),
            $ltiResultSenderRegistry,
            static::getContainer()->get(TestSessionInitiator::class),
            static::getContainer()->get(MessageBusInterface::class),
            static::getContainer()->get(EventDispatcherInterface::class),
        );
    }

    private function createSubjectForAgsSend(): ResultExtractionMessageHandler
    {
        $ltiResultSenderRegistry = new LtiResultSenderRegistry([$this->createLtiAgsSender()]);

        return $this->createResultExtractionMessageHandler($ltiResultSenderRegistry);
    }

    private function createResultExtractionMessageHandler(
        LtiResultSenderRegistry $ltiResultSenderRegistry,
    ): ResultExtractionMessageHandler {

        return new ResultExtractionMessageHandler(
            static::getContainer()->get(LoggerAwareDeliveryExecutionService::class),
            static::getContainer()->get(ExtractDeliveryExecutionResultService::class),
            static::getContainer()->get(DeliveryExecutionClosureService::class),
            static::getContainer()->get(LoggerInterface::class),
            $ltiResultSenderRegistry,
            static::getContainer()->get(TestSessionInitiator::class),
            static::getContainer()->get(MessageBusInterface::class),
            static::getContainer()->get(EventDispatcherInterface::class),
        );
    }

    private function createLtiAgsSender(): LtiAgsScoreSender
    {
        $ltiAgsScoreService = new LtiAgsScoreService(
            $this->ltiAgsClientMock,
            $this->registrationRepositoryMock,
            static::getContainer()->get(ScoreFactory::class),
        );

        return new LtiAgsScoreSender(
            static::getContainer()->get(ScoringEligibilityChecker::class),
            $ltiAgsScoreService,
            static::getContainer()->get('monolog.logger.audit_platform'),
            static::getContainer()->get(FeatureFlagAdapterInterface::class),
        );
    }

    private function getDeliveryExecutionWithStartedTestSession(
        string $deliveryExecutionStatus,
        ?DateTimeInterface $startedAt = null,
        ?DateTimeInterface $endedAt = null,
        array $ltiParameters = [
            'lis_outcome_service_url' => 'https://www.test.com',
            'oauth_consumer_key' => 'aaa1',
            'result_id' => 'test_taker_id',
        ],
        bool $sessionStarted = true,
    ): DeliveryExecution {
        $this->copyCompiledTestToStorage();

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
            '',
            new DeliveryExecutionExtraStateData(),
            $deliveryExecutionStatus,
            $startedAt,
            $endedAt,
        );

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($sessionStarted) {
            $testSession->beginTestSession();
            $testSession->beginAttempt();
        }

        $deliveryExecutionPropertyService->persistTestSession($testSession);

        return $deliveryExecution;
    }

    private function normalizeResultId(string $resultId): string
    {
        return preg_replace('~[/\\\\]~', '_', $resultId) . '.xml';
    }

    /**
     * @return void
     */
    public function mockRegistrationRepository(): void
    {
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn($this->getLtiRegistration());
    }

    /**
     * @return void
     */
    public function mockRegistrationRepositoryWithEmpty(): void
    {
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn(null);
    }

    private function getLtiRegistration(): Registration
    {
        return new Registration(
            'reg-1',
            'client-1',
            new Platform('platform-1', 'Platform 1', 'platform-1'),
            new Tool('tool-1', 'Tool 1', 'tool-1', 'https://localhost'),
            ['deploy-1', 'deploy-2'],
            new KeyChain(
                'platform-key-1',
                'platform-keys',
                new Key('public-key'),
                new Key('private-key', 'private-pass'),
            ),
            new KeyChain(
                'platform-key-1',
                'platform-keys',
                new Key('public-key'),
                new Key('private-key', 'private-pass'),
            ),
        );
    }
}
