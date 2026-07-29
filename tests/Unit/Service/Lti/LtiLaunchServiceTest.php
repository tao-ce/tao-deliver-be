<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lti;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Lti\Response\LtiForwardResponse;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Repository\DeliveryRepository;
use App\Service\ApplicationInfoService;
use App\Service\Battery\BatteryService;
use App\Domain\Battery\Model\Battery;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Lti\Dto\StartProctoringRequestContext;
use App\Service\Lti\LtiLaunchService;
use App\Service\Lti\LtiProctoringService;
use App\Service\Lti\LtiTokenResolver;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Service\TestSessionInitiator;
use App\TestRunner\Service\TestSessionNavigator;
use App\Tests\Traits\AgsTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Tests\Traits\RegistrationRepositoryTestingTrait;
use Carbon\Carbon;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\EnvironmentManagementClientBundle\Http\ResponseHelper;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayload;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LtiLaunchServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use MessengerTestingTrait;
    use OAuth2SecurityTestingTrait;
    use QtiTestingTrait;
    use RegistrationRepositoryTestingTrait;
    use AgsTestingTrait;

    private const EXPECTED_PROCTORING_URL = 'https://proctoring.tool';
    private const EXPECTED_FRONTEND_URL = 'http://frontend-url/';

    private DeliveryRepository $deliveryRepositoryMock;
    private DeliveryExecutionServiceInterface $deliveryExecutionServiceMock;
    private TestSessionInitiator $testSessionInitiatorMock;
    private LtiLaunchService $subject;
    private LtiMessagePayloadInterface $ltiMessagePayload;
    private LtiProctoringService $ltiProctoringServiceMock;
    private LoggerInterface $loggerMock;
    private EventDispatcherInterface $eventDispatcher;
    private LtiExtraTimeHandler|MockObject $ltiExtraTimeHandlerMock;
    private bool $isDeliveryExecutionCreatedEventDispatched;

    public function setUp(): void
    {
        self::bootKernel();
        $this->setUpTestMessageBus();
        $this->mockRegistrationRepository();

        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->testSessionInitiatorMock = $this->createMock(TestSessionInitiator::class);
        $this->ltiMessagePayload = $this->createMock(LtiMessagePayloadInterface::class);

        $uuidGeneratorMock = $this->createMock(UuidGenerator::class);
        $uuidGeneratorMock
            ->method('generate')
            ->willReturn('uuid-generated');

        $parameterBag = $this->createMock(ParameterBag::class);

        $parameterBag
            ->method('get')
            ->with('deliver.frontend.url')
            ->willReturn(self::EXPECTED_FRONTEND_URL);

        $this->ltiProctoringServiceMock = $this->createMock(LtiProctoringService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
        $this->ltiExtraTimeHandlerMock = $this->createMock(LtiExtraTimeHandler::class);

        $this->isDeliveryExecutionCreatedEventDispatched = false;

        $this->subject = new LtiLaunchService(
            $this->createMock(BatteryService::class),
            $this->deliveryRepositoryMock,
            $this->createMock(FilesystemReader::class),
            $this->testSessionInitiatorMock,
            $this->createMock(TestSessionNavigator::class),
            $parameterBag,
            $this->deliveryExecutionServiceMock,
            $this->loggerMock,
            $this->getContainer()->get(LtiCustomSettings::class),
            $this->getContainer()->get(LtiTokenResolver::class),
            $this->ltiProctoringServiceMock,
            static::getContainer()->get(ResponseHelper::class),
            static::getContainer()->get(ApplicationInfoService::class),
            $this->eventDispatcher,
            $this->ltiExtraTimeHandlerMock,
            $this->createMock(ConfigurationRepositoryInterface::class),
        );
    }

    /**
     * @dataProvider providerTestItForcesTestSessionInitialization
     */
    public function testItForcesTestSessionInitialization(
        array $ltiParameters,
        string $status,
        bool $forceReinitialization,
    ): void {
        $this->mockPublishScore($this->never());
        $delivery = $this->createTestDelivery('CompactTest');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $deliveryExecution = $this->createDeliveryExecutionMock(
            'userId#deliveryId#resultId#tenantId',
            'CompactTest',
            'tenantId',
            $ltiParameters,
            null,
            null,
            $status,
        );

        $this->testSessionInitiatorMock->expects(self::once())
            ->method('init')
            ->with($deliveryExecution, $forceReinitialization);

        $this->ltiExtraTimeHandlerMock->expects(self::once())
            ->method('addExtraTime')
            ->with($deliveryExecution);

        $this->subject->launch('CompactTest', $ltiParameters, $this->ltiMessagePayload);
    }

    public function providerTestItForcesTestSessionInitialization(): array
    {
        $forceResumeLtiParameter = [
            "client_id" => '1',
            "user_id" => '1',
            'custom' => [LtiCustomSettings::PARAM_FORCE_RESUME => true],
        ];

        return [
            'Resume a closed Delivery Execution' => [
                ["client_id" => '1', "user_id" => '1'],
                DeliveryExecution::STATUS_CLOSED,
                false,
            ],
            'Force resume a closed Delivery Execution' => [
                $forceResumeLtiParameter,
                DeliveryExecution::STATUS_CLOSED,
                true,
            ],
            'For resume an opened Delivery Execution' => [
                $forceResumeLtiParameter,
                DeliveryExecution::STATUS_INTERACTING,
                false,
            ],
        ];
    }

    public function testItSendsAgsStatusStarted(): void
    {
        $this->mockPublishScore();
        $delivery = $this->createTestDelivery('CompactTest');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

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

        $this->createDeliveryExecutionMock(
            'userId#CompactTest#resultId#tenantId',
            'CompactTest',
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch('CompactTest', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItSendsAgsStatusStartedAtResetLaunch(): void
    {
        $this->mockPublishScore();
        $delivery = $this->createTestDelivery('CompactTest');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

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
            'custom' => [
                LtiCustomSettings::PARAM_RESET => 'true',
            ],
        ];

        $this->createDeliveryExecutionMock(
            'userId#CompactTest#resultId#tenantId',
            'CompactTest',
            'tenantId',
            $ltiParameters,
        );

        $this->subject->launch(
            'CompactTest',
            $ltiParameters,
            $this->ltiMessagePayload,
        );
    }

    public function testItSkipsAgsStatusStartedOnForceResumeLaunch(): void
    {
        $this->mockPublishScore();
        $delivery = $this->createTestDelivery('CompactTest');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

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
            'custom' => [
                LtiCustomSettings::PARAM_FORCE_RESUME => 'true',
            ],
        ];

        $deliveryExecution = $this->createDeliveryExecutionMock(
            'userId#CompactTest#resultId#tenantId',
            'CompactTest',
            'tenantId',
            $ltiParameters,
        );

        $this->subject->launch(
            'CompactTest',
            $ltiParameters,
            $this->ltiMessagePayload,
        );

        $deliveryExecution->close();
        $this->subject->launch(
            'CompactTest',
            $ltiParameters,
            $this->ltiMessagePayload,
        );
    }

    public function testItSkipsSendingAgsStatusStarted(): void
    {
        $this->mockPublishScore($this->never());
        $delivery = $this->createTestDelivery('CompactTest');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
        ];

        $this->createDeliveryExecutionMock(
            'userId#CompactTest#resultId#tenantId',
            'CompactTest',
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch('CompactTest', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItStartsReviewingOnAutoReview(): void
    {
        $this->mockPublishScore($this->never());
        $tenantId = 'tenantId';
        $deliveryId = 'CompactTest';
        $deliveryExecutionId = "userId#$deliveryId#resultId#$tenantId";
        $delivery = $this->createTestDelivery($deliveryId);
        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'id_token' => $this->createOAuth2AccessToken($deliveryExecutionId),
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                LtiCustomSettings::PARAM_AUTO_REVIEW_MODE => true,
            ],
        ];

        $deliveryExecution = $this->createTestDeliveryExecution(
            $deliveryExecutionId,
            'CompactTest',
            $tenantId,
            $ltiParameters,
            null,
            status: DeliveryExecution::STATUS_CLOSED,
        );

        $this->deliveryExecutionServiceMock->method('getDeliveryExecution')->willReturn($deliveryExecution);
        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('createDeliveryExecution')
            ->with(
                $delivery,
                "review#$deliveryExecutionId",
                [...$ltiParameters, 'result_id' => $deliveryExecutionId],
            );

        $this->subject->launch('CompactTest', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItThrowsErrorWhenCannotNavigateToItemRef(): void
    {
        $this->mockPublishScore($this->never());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            '[IRRECOVERABLE] Unable to find the item identifier wrongItemId to reach provided in LTI custom claims',
        );

        $delivery = $this->createTestDelivery('CompactTest');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                'deliverySettings.item.id' => 'wrongItemId',
            ],
        ];

        $this->createDeliveryExecutionMock(
            'userId#CompactTest#resultId#tenantId',
            'CompactTest',
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch('CompactTest', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testAnonymousLaunchWithProctoring(): void
    {
        $this->mockPublishScore($this->never());
        $ltiParameters = [
            'user_id' => 'anonymous',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'context_id' => 'test',
            'resource_link_id' => 'resourceLinkId',
            'custom' => [
                'proctoringSettings.enableMonitoring' => 'true',
            ],
        ];

        $this->ltiMessagePayload
            ->expects($this->never())
            ->method('getUserIdentity')
            ->willReturn(null);

        $propertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        self::getContainer()->set(DeliveryExecutionPropertyService::class, $propertyServiceMock);

        $delivery = $this->createTestDelivery('CompactTest');
        $this->copyCompiledTestToStorage();

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $this->ltiProctoringServiceMock
            ->expects(self::once())
            ->method('getStartProctoringRequestUrl')
            ->willReturn(self::EXPECTED_PROCTORING_URL);

        $deliveryExecution = $this->createDeliveryExecutionMock(
            'suomynona#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
        );

        $this->deliveryExecutionServiceMock
            ->expects(self::exactly(1))
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $response = $this->subject->launch('CompactTest', $ltiParameters, $this->ltiMessagePayload);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertEquals(
            self::EXPECTED_PROCTORING_URL,
            $response->getTargetUrl(),
        );
    }

    public function testLaunchWithProctoring(): void
    {
        $this->mockPublishScore($this->never());
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'context_id' => 'test',
            'resource_link_id' => 'resourceLinkId',
            'custom' => [
                'proctoringSettings.enableMonitoring' => 'true',
            ],
        ];

        $this->ltiMessagePayload
            ->expects($this->never())
            ->method('getUserIdentity')
            ->willReturn(new UserIdentity('userId'));

        $delivery = $this->createTestDelivery('Basic');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $this->copyCompiledTestToStorage();
        $deliveryExecution = $this->createDeliveryExecutionMock(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
            null,
        );
        $ltiParameters['result_id'] = $deliveryExecution->getId();
        $this->deliveryExecutionServiceMock
            ->expects(self::exactly(1))
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $this->loggerMock
            ->expects(self::exactly(1))
            ->method('info')
            ->with(
                sprintf(
                    '[userId#Basic#resultId#tenantId] - redirected to start proctoring: %s',
                    self::EXPECTED_PROCTORING_URL,
                ),
            );

        $this->ltiProctoringServiceMock
            ->expects(self::exactly(1))
            ->method('getStartProctoringRequestUrl')
            ->with(
                new StartProctoringRequestContext(
                    $this->ltiMessagePayload,
                    $deliveryExecution,
                    $delivery,
                    $ltiParameters,
                ),
            )->willReturn(self::EXPECTED_PROCTORING_URL);
        $response = $this->subject->launch('Basic', $ltiParameters, $this->ltiMessagePayload);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertEquals(self::EXPECTED_PROCTORING_URL, $response->getTargetUrl());
    }

    public function testStandardLaunchWithDeprecatedRequiredAuthorizationClaim(): void
    {
        $this->mockPublishScore($this->never());
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                'proctoringSettings.requireAuthorization' => 'true',
            ],
        ];

        $this->ltiProctoringServiceMock
            ->expects(self::never())
            ->method('getStartProctoringRequestUrl');

        $delivery = $this->createTestDelivery('Basic');

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $deliveryExecution = $this->createDeliveryExecutionMock(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
        );

        $this->deliveryExecutionServiceMock
            ->expects(self::exactly(1))
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $this->testSessionInitiatorMock->expects(self::once())
            ->method('init')
            ->with($deliveryExecution, false);

        $response = $this->subject->launch('Basic', $ltiParameters, $this->ltiMessagePayload);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertEquals(
            sprintf(
                '%s?deliveryExecutionId=%s',
                self::EXPECTED_FRONTEND_URL,
                rawurlencode(rawurlencode('userId#Basic#resultId#tenantId')) .
                '&refreshTokenId=userId%2523Basic%2523resultId%2523tenantId',
            ),
            $response->getTargetUrl(),
        );
    }

    public function testItLaunchesWithStartAndEndDate(): void
    {
        $this->mockPublishScore($this->never());
        $delivery = $this->createTestDelivery(
            'Basic',
            configuration: [
                'expiryDate' => Carbon::tomorrow()->getTimestamp(),
                'availabilityDate' => Carbon::yesterday()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
        ];

        $this->createDeliveryExecutionMock(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch('Basic', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItLaunchesReviewWhenStartDateIsInTheFuture(): void
    {
        $this->mockPublishScore($this->never());
        $delivery = $this->createTestDelivery(
            'Basic',
            configuration: [
                'availabilityDate' => Carbon::tomorrow()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                LtiCustomSettings::PARAM_REVIEW_MODE => 'true',
            ],
        ];

        $this->createDeliveryExecutionMock(
            'review#userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch('Basic', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItLaunchesReviewWhenEndDateInThePast(): void
    {
        $this->mockPublishScore($this->never());
        $delivery = $this->createTestDelivery(
            'Basic',
            configuration: [
                'expiryDate' => Carbon::yesterday()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                LtiCustomSettings::PARAM_REVIEW_MODE => 'true',
            ],
        ];

        $this->createDeliveryExecutionMock(
            'review#userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch('Basic', $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItThrowsErrorWhenStartDateInTheFuture(): void
    {
        $this->mockPublishScore($this->never());
        $deliveryId = 'Basic';

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage(
            "[RECOVERABLE] Delivery ID $deliveryId can not be launched due to start and end dates configuration",
        );

        $delivery = $this->createTestDelivery(
            $deliveryId,
            configuration: [
                'availabilityDate' => Carbon::tomorrow()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
        ];

        $this->createDeliveryExecutionMock(
            'userId#Basic#resultId#tenantId',
            $deliveryId,
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch($deliveryId, $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItThrowsErrorWhenEndDateInThePast(): void
    {
        $this->mockPublishScore($this->never());
        $deliveryId = 'Basic';

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage(
            "[IRRECOVERABLE] Delivery ID $deliveryId can not be launched due to start and end dates configuration",
        );

        $delivery = $this->createTestDelivery(
            $deliveryId,
            configuration: [
                'expiryDate' => Carbon::yesterday()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
        ];

        $this->createDeliveryExecutionMock(
            'userId#Basic#resultId#tenantId',
            $deliveryId,
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch($deliveryId, $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItThrowsErrorForAnonymousReviewWhenStartDateInTheFuture(): void
    {
        $this->mockPublishScore($this->never());
        $deliveryId = 'Basic';

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage(
            "[RECOVERABLE] Delivery ID $deliveryId can not be launched due to start and end dates configuration",
        );

        $delivery = $this->createTestDelivery(
            $deliveryId,
            configuration: [
                'availabilityDate' => Carbon::tomorrow()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                LtiCustomSettings::PARAM_REVIEW_MODE => 'true',
            ],
        ];

        $this->createDeliveryExecutionMock(
            'suomynona#Basic#resultId#tenantId',
            $deliveryId,
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch($deliveryId, $ltiParameters, $this->ltiMessagePayload);
    }

    public function testItThrowsErrorForAnonymousReviewWhenEndDateInThePast(): void
    {
        $this->mockPublishScore($this->never());
        $deliveryId = 'Basic';

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage(
            "[IRRECOVERABLE] Delivery ID $deliveryId can not be launched due to start and end dates configuration",
        );

        $delivery = $this->createTestDelivery(
            $deliveryId,
            configuration: [
                'expiryDate' => Carbon::yesterday()->getTimestamp(),
            ],
        );

        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $ltiParameters = [
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'custom' => [
                LtiCustomSettings::PARAM_REVIEW_MODE => 'true',
            ],
        ];

        $this->createDeliveryExecutionMock(
            'suomynona#Basic#resultId#tenantId',
            $deliveryId,
            'tenantId',
            $ltiParameters,
            null,
        );

        $this->subject->launch($deliveryId, $ltiParameters, $this->ltiMessagePayload);
    }

    /**
     * @dataProvider providerTestRequireAuthorizationRedirect
     */
    public function testRequireAuthorizationRedirect(string $redirectResponseClass, bool $redirect)
    {
        $ltiParameters = [
            'user_id' => 'user_id',
            'platform_issuer' => 'platform_issuer',
            'client_id' => 'client_id',
            'context_id' => 'test',
            'resource_link_id' => 'resourceLinkId',
            'custom' => [
                'proctoringSettings.enableMonitoring' => 'true',
            ],
        ];

        $this->copyCompiledTestToStorage();
        $deliveryExecution = $this->createDeliveryExecutionMock(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            $ltiParameters,
            null,
        );
        $startProctoringRequestContext = new StartProctoringRequestContext(
            $this->createMock(LtiMessagePayload::class),
            $deliveryExecution,
            $this->createMock(Delivery::class),
            $ltiParameters,
        );

        $this->deliveryExecutionServiceMock
            ->expects(self::exactly(1))
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $this->ltiProctoringServiceMock
            ->method('getStartProctoringRequestUrl')
            ->with($startProctoringRequestContext)
            ->willReturn(self::EXPECTED_PROCTORING_URL);

        $redirectResponse = $this->subject->requireAuthorization(
            $startProctoringRequestContext,
            $redirect,
        );

        $this->assertInstanceOf($redirectResponseClass, $redirectResponse);
    }

    private function providerTestRequireAuthorizationRedirect(): array
    {
        return [
            'RedirectResponse' => [
                RedirectResponse::class,
                true,
            ],
            'LtiForwardResponse' => [
                LtiForwardResponse::class,
                false,
            ],
        ];
    }

    private function createDeliveryExecutionMock(
        string $id = 'userId#deliveryId#resultId#tenantId',
        string $deliveryId = 'deliveryId',
        string $tenantId = 'tenantId',
        array $ltiLaunchParameters = ['ltiLaunchParams'],
        ?string $testSession = 'testSession',
        ?DeliveryExecutionExtraStateData $extraStateData = null,
        string $status = DeliveryExecution::STATUS_INITIAL,
    ): DeliveryExecution {
        $deliveryExecution = $this->createTestDeliveryExecution(
            $id,
            $deliveryId,
            $tenantId,
            $ltiLaunchParameters,
            $testSession,
            $extraStateData,
            $status,
        );
        $this->deliveryExecutionServiceMock->method('getDeliveryExecution')->willReturnCallback(
            function () use ($deliveryExecution) {
                if (!$this->isDeliveryExecutionCreatedEventDispatched) {
                    $this->eventDispatcher->dispatch(new DeliveryExecutionCreatedEvent($deliveryExecution));
                }
                $this->isDeliveryExecutionCreatedEventDispatched = true;
                return $deliveryExecution;
            },
        );
        return $deliveryExecution;
    }

    public function testGetCommonLocalesWithNoParameters(): void
    {
        $refMethod = new ReflectionMethod(LtiLaunchService::class, 'getCommonLocales');

        $supportedLocales = ['en-US', 'fr-FR'];
        $delivery = $this->createTestDelivery('deliveryId', supportedLocales: $supportedLocales);

        $result = $refMethod->invoke($this->subject, $delivery, []);

        $this->assertEquals($supportedLocales, $result);
    }

    public function testGetCommonLocalesWithNoDeliveryAndNoParameters(): void
    {
        $refMethod = new ReflectionMethod(LtiLaunchService::class, 'getCommonLocales');

        $result = $refMethod->invoke($this->subject, null, []);

        $this->assertEquals([], $result);
    }

    public function testGetCommonLocalesWithBatteryId(): void
    {
        $refMethod = new ReflectionMethod(LtiLaunchService::class, 'getCommonLocales');

        $supportedLocales = ['en-US', 'fr-FR'];
        $delivery = $this->createTestDelivery('deliveryId', supportedLocales: $supportedLocales);

        $batteryId = 'batteryId';
        $battery = $this->createMock(Battery::class);

        $batteryServiceMock = $this->createMock(BatteryService::class);
        $batteryServiceMock
            ->expects($this->once())
            ->method('findBatteryOrFail')
            ->with($batteryId)
            ->willReturn($battery);

        $batteryServiceMock
            ->expects($this->once())
            ->method('getCommonLocales')
            ->with($battery)
            ->willReturn(['fr-FR']);

        $property = new ReflectionProperty(LtiLaunchService::class, 'batteryService');
        $property->setValue($this->subject, $batteryServiceMock);

        $result = $refMethod->invoke($this->subject, $delivery, ['battery_id' => $batteryId]);

        $this->assertEquals(['fr-FR'], $result);
    }

    public function testLaunchTestUsesDeliverySupportedLocalesWhenNoBatteryId(): void
    {
        $supportedLocales = ['en-US', 'fr-FR'];
        $delivery = $this->createTestDelivery('deliveryId', supportedLocales: $supportedLocales);

        $ltiParameters = [
            'user_id' => 'user_id',
            'client_id' => 'client_id',
        ];

        $refMethod = new ReflectionMethod(LtiLaunchService::class, 'getCommonLocales');

        $result = $refMethod->invoke($this->subject, $delivery, $ltiParameters);

        $this->assertEquals(
            $supportedLocales,
            $result,
            'getCommonLocales should return the delivery\'s supported locales when battery_id is not provided',
        );
    }
}
