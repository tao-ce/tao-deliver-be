<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Sender;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\Sender\LtiAgsScoreSender;
use App\Messenger\Message\ResultExtractionMessage;
use App\Service\DeliveryExecution\ScoringEligibilityChecker;
use App\Service\Lti\LtiAgsScoreService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use Carbon\Carbon;
use Monolog\Logger;
use OAT\Library\EnvironmentManagementLtiClient\Client\LtiAgsClient;
use OAT\Library\Lti1p3Ags\Factory\Score\ScoreFactory;
use OAT\Library\Lti1p3Ags\Model\Score\Score;
use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\Key;
use OAT\Library\Lti1p3Core\Security\Key\KeyChain;
use OAT\Library\Lti1p3Core\Tool\Tool;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LtiAgsScoreSenderTest extends KernelTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;

    /** @var LtiAgsScoreSender */
    private $subject;

    /** @var ScoringEligibilityChecker|MockObject */
    private $scoringEligibilityCheckerMock;

    /** @var LtiAgsClient|MockObject */
    private $ltiAgsClient;

    /** @var RegistrationRepositoryInterface|MockObject */
    private $registrationRepositoryMock;

    /** @var ScoreFactory|MockObject */
    private $scoreFactoryMock;

    /** @var LtiAgsScoreService */
    private $ltiAgsScoreService;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestLogHandler();

        $this->scoringEligibilityCheckerMock = $this->createMock(ScoringEligibilityChecker::class);
        $this->ltiAgsClient = $this->createMock(LtiAgsClient::class);
        $this->registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $this->scoringEligibilityCheckerMock = $this->createMock(ScoringEligibilityChecker::class);
        $this->ltiAgsClient = $this->createMock(LtiAgsClient::class);
        $this->scoreFactoryMock = $this->createMock(ScoreFactory::class);

        $this->ltiAgsScoreService = new LtiAgsScoreService(
            $this->ltiAgsClient,
            $this->registrationRepositoryMock,
            $this->scoreFactoryMock,
        );

        /** @var LoggerInterface $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);

        $this->subject = new LtiAgsScoreSender(
            $this->scoringEligibilityCheckerMock,
            $this->ltiAgsScoreService,
            $logger,
            static::getContainer()->get(FeatureFlagAdapterInterface::class),
        );
    }

    public function testIfRegistrationNotFound(): void
    {
        $ltiParameters = [
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'ags_claim' => [
                'scope' => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/score',
                ],
                'lineitems' => 'https://taotesting.com/agsContextId/lineitems',
                'lineitem' => 'https://taotesting.com/agsContextId/lineitems/foo',
            ],
        ];

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to get registration id associated to LTI launch');

        $this->subject->send(
            $this->getDeliveryExecution($ltiParameters),
            ['totalScore' => 1, 'maxScore' => 3],
            $this->getResultExtractionMessage(),
        );
    }

    public function testIfAgsClaimNotFound(): void
    {
        $ltiParameters = [];

        $this->subject->send(
            $this->getDeliveryExecution($ltiParameters),
            ['totalScore' => 1, 'maxScore' => 3],
            $this->getResultExtractionMessage(),
        );

        $this->ltiAgsClient
            ->expects($this->never())
            ->method('publishScore');
    }

    /** @dataProvider dataProvider */
    public function testSend(bool $isEligible, string $gradingProgress): void
    {
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'ags_claim' => [
                'scope' => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/score',
                ],
                'lineitems' => 'https://taotesting.com/agsContextId/lineitems',
                'lineitem' => 'https://taotesting.com/agsContextId/lineitems/foo',
            ],
        ];

        $this->scoringEligibilityCheckerMock
            ->method('isEligible')
            ->willReturn($isEligible);

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn($this->getLtiRegistration());

        $deliveryExecution = $this->getDeliveryExecution($ltiParameters);

        $scoreMock = $this->createMock(Score::class);
        $this->scoreFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with([
                'userId' => 'user_id',
                'activityProgress' => Score::ACTIVITY_PROGRESS_STATUS_COMPLETED,
                'gradingProgress' => $gradingProgress,
                'scoreGiven' => 12.34,
                'scoreMaximum' => 56.78,
                'timestamp' => $deliveryExecution->getFinishedAt(),
            ])
            ->willReturn($scoreMock);

        $this->ltiAgsClient
            ->expects($this->once())
            ->method('publishScore');

        $this->subject->send(
            $deliveryExecution,
            [
                'score' => 0.27,
                'maxScore' => 56.78,
                'totalScore' => 12.34,
            ],
            $this->getResultExtractionMessage(),
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] LTI AGS score publication was successful',
            Logger::INFO,
        );
    }

    public function testSendWithoutExternalGrader(): void
    {
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'ags_claim' => [
                'scope' => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/score',
                ],
                'lineitems' => 'https://taotesting.com/agsContextId/lineitems',
                'lineitem' => 'https://taotesting.com/agsContextId/lineitems/foo',
            ],
        ];

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn($this->getLtiRegistration());

        $deliveryExecution = $this->getDeliveryExecution(
            $ltiParameters,
            'userId#deliveryId#resultId#tenantId',
            'tenantId1',
        );

        $scoreMock = $this->createMock(Score::class);
        $this->scoreFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with([
                'userId' => 'user_id',
                'activityProgress' => Score::ACTIVITY_PROGRESS_STATUS_COMPLETED,
                'gradingProgress' => Score::GRADING_PROGRESS_STATUS_FULLY_GRADED,
                'scoreGiven' => 12.34,
                'scoreMaximum' => 56.78,
                'timestamp' => $deliveryExecution->getFinishedAt(),
            ])
            ->willReturn($scoreMock);

        $this->ltiAgsClient
            ->expects($this->once())
            ->method('publishScore')
            ->with($this->isType('string'), $scoreMock, $this->isType('string'));

        $this->scoringEligibilityCheckerMock
            ->expects($this->never())
            ->method('isEligible');

        $subject = new LtiAgsScoreSender(
            $this->scoringEligibilityCheckerMock,
            $this->ltiAgsScoreService,
            static::getContainer()->get('logger'),
            static::getContainer()->get(FeatureFlagAdapterInterface::class),
        );

        $subject->send(
            $deliveryExecution,
            [
                'score' => 0.27,
                'maxScore' => 56.78,
                'totalScore' => 12.34,
            ],
            $this->getResultExtractionMessage(),
        );

        $this->assertHasLogRecordWithMessage(
            "[{$deliveryExecution->getId()}] LTI AGS score publication was successful",
            Logger::INFO,
        );
    }

    public function dataProvider(): array
    {
        return [
            'eligible' => [true, Score::GRADING_PROGRESS_STATUS_NOT_READY],
            'non-eligible' => [false, Score::GRADING_PROGRESS_STATUS_FULLY_GRADED],
        ];
    }

    public function testSkipSendingUponMissingScope(): void
    {
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'ags_claim' => [
                'scope' => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                ],
                'lineitems' => 'https://taotesting.com/agsContextId/lineitems',
                'lineitem' => 'https://taotesting.com/agsContextId/lineitems/foo',
            ],
        ];

        $this->ltiAgsClient
            ->expects($this->never())
            ->method('publishScore');

        $this->subject->send(
            $this->getDeliveryExecution($ltiParameters),
            [
                'score' => 0.27,
                'maxScore' => 56.78,
                'totalScore' => 12.34,
            ],
            $this->getResultExtractionMessage(),
        );

        $this->assertHasNoLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] LTI AGS score publication was successful',
            Logger::INFO,
        );
    }

    public function testSkipSendingUponInvalidAgsClaim(): void
    {
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'ags_claim' => ['foo'],
        ];

        $this->ltiAgsClient
            ->expects($this->never())
            ->method('publishScore');

        $this->subject->send(
            $this->getDeliveryExecution($ltiParameters),
            [
                'score' => 0.27,
                'maxScore' => 56.78,
                'totalScore' => 12.34,
            ],
            $this->getResultExtractionMessage(),
        );

        $this->assertHasNoLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] LTI AGS score publication was successful',
            Logger::INFO,
        );
    }

    public function testSendIfPublishFails(): void
    {
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'ags_claim' => [
                'scope' => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/score',
                ],
                'lineitems' => 'https://taotesting.com/agsContextId/lineitems',
                'lineitem' => 'https://taotesting.com/agsContextId/lineitems/foo',
            ],
        ];

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn($this->getLtiRegistration());

        $deliveryExecution = $this->getDeliveryExecution($ltiParameters);

        $scoreMock = $this->createMock(Score::class);
        $this->scoreFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with([
                'userId' => 'user_id',
                'activityProgress' => Score::ACTIVITY_PROGRESS_STATUS_COMPLETED,
                'gradingProgress' => Score::GRADING_PROGRESS_STATUS_FULLY_GRADED,
                'scoreGiven' => 12.34,
                'scoreMaximum' => 56.78,
                'timestamp' => $deliveryExecution->getFinishedAt(),
            ])
            ->willReturn($scoreMock);

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->willReturn($this->getLtiRegistration());

        $this->ltiAgsClient
            ->expects($this->once())
            ->method('publishScore')
            ->willThrowException(new RuntimeException('LTI AGS score publication failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LTI AGS score publication failed');

        $this->subject->send(
            $deliveryExecution,
            [
                'score' => 0.27,
                'maxScore' => 56.78,
                'totalScore' => 12.34,
            ],
            $this->getResultExtractionMessage(),
        );
    }

    private function getDeliveryExecution(
        array $ltiParameters,
        $id = 'userId#deliveryId#resultId#tenantId',
        string $tenantId = 'tenantId',
    ): DeliveryExecution {
        return $this->createTestDeliveryExecution(
            $id,
            'deliveryId',
            $tenantId,
            $ltiParameters,
            null,
            null,
            DeliveryExecution::STATUS_INITIAL,
            Carbon::today(),
            Carbon::today(),
        );
    }

    private function getResultExtractionMessage(
        string $id = 'id',
        string $deliveryExecutionId = 'deliveryExecutionId',
    ): ResultExtractionMessage {
        return new ResultExtractionMessage($id, $deliveryExecutionId);
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
                'tool-key-1',
                'tool-keys',
                new Key('public-key'),
                new Key('private-key', 'private-pass'),
            ),
        );
    }
}
