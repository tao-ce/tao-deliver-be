<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Repository\DeliveryExecutionAlias\Contract\DeliveryExecutionIdentifierAliasRepositoryInterface;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\DeliveryExecutionDeleter;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Service\Lti\LtiTokenResolver;
use App\TestRunner\ActionProcessor\Exception\ConflictException;
use App\TestRunner\Service\TestSessionInitiator;
use App\Tests\Traits\DataStoreTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Lock\LockFactory;

class DeliveryExecutionServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use DataStoreTestingTrait;

    private DeliveryExecutionRepository $repository;
    private DeliveryExecutionIdentifierAliasRepositoryInterface|MockObject $aliasRepository;
    private DeliveryExecutionDeleter|MockObject $deliveryExecutionDeleterMock;
    private TestSessionInitiator|MockObject $testSessionInitiator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(DeliveryExecutionRepository::class);
        $this->aliasRepository = $this->createMock(DeliveryExecutionIdentifierAliasRepositoryInterface::class);
        $this->deliveryExecutionDeleterMock = $this->createMock(DeliveryExecutionDeleter::class);
        $this->testSessionInitiator = $this->createMock(TestSessionInitiator::class);
    }

    public function testItDoesNotFetchDeliveryExecutionAndCreateIt(): void
    {
        $delivery = $this->createTestDelivery();
        $this->repository
            ->expects($this->never())
            ->method('find');

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            [],
        );

        $this->assertInstanceOf(DeliveryExecution::class, $deliveryExecution);
    }

    public function dataProvider(): array
    {
        return [
            [
                'lisResultSourcedId',
                'userId',
            ],
            [
                null,
                'userId',
            ],
            [
                'lisResultSourcedId',
                null,
            ],
        ];
    }

    public function testItCanCreateReviewableDeliveryExecution(): void
    {
        $publishedDeliveryTime = Carbon::createFromFormat(DateTimeInterface::RFC3339, '2021-01-01T00:00:00Z');
        $delivery = $this->createTestDelivery(
            createdAt: $publishedDeliveryTime,
        );
        $expectedDeliveryExecution = $this->createTestDeliveryExecution(testSession: null);
        $expectedDeliveryExecution->setDeliveryPublicationTime($publishedDeliveryTime);

        $this->repository->method('find')
            ->willReturn($expectedDeliveryExecution);

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParametersForReview(),
        );


        $this->assertNotEquals($expectedDeliveryExecution->getId(), $deliveryExecution->getId());
        $this->assertStringContainsString('review#', $deliveryExecution->getId());
        $this->assertEquals($expectedDeliveryExecution->getExtraStateData(), $deliveryExecution->getExtraStateData());
        $this->assertEquals($expectedDeliveryExecution->getQtiSdkEncodedTestSession(), $deliveryExecution->getQtiSdkEncodedTestSession());
    }

    public function testItThrowsExceptionIfNotFoundDeliveryExecutionForReview(): void
    {
        $delivery = $this->createTestDelivery();

        $this->repository->method('find')
            ->willThrowException(new DocumentNotFoundException());

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Not found delivery execution for provided test taker');

        $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParametersForReview(),
        );
    }

    public function testItCanResume(): void
    {
        $delivery = $this->createTestDelivery();

        $expectedDeliveryExecution = $this->createTestDeliveryExecution();
        $this->repository
            ->expects($this->once())
            ->method('find')
            ->willReturn($expectedDeliveryExecution);

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            [
                'user_id' => 'userId',
            ],
        );

        $this->assertSame($expectedDeliveryExecution, $deliveryExecution);
    }

    public function testItThrowsExceptionOnResumeIfNoAttemptsLeft(): void
    {
        $delivery = $this->createTestDelivery();

        $expectedDeliveryExecution = $this->createTestDeliveryExecution();
        $expectedDeliveryExecution->setStatus(DeliveryExecution::STATUS_CLOSED);
        $this->repository
            ->expects($this->once())
            ->method('find')
            ->willReturn($expectedDeliveryExecution);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('You don’t have any more attempts for this test');

        $this->createSubject()->getDeliveryExecution(
            $delivery,
            [
                'user_id' => 'userId',
            ],
        );
    }

    public function testItResumesOnAutoReview(): void
    {
        $delivery = $this->createTestDelivery();

        $expectedDeliveryExecution = $this->createTestDeliveryExecution();
        $expectedDeliveryExecution->setStatus(DeliveryExecution::STATUS_CLOSED);
        $this->repository
            ->expects($this->once())
            ->method('find')
            ->willReturn($expectedDeliveryExecution);

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            [
                'user_id' => 'userId',
                'custom' => [
                    LtiCustomSettings::PARAM_AUTO_REVIEW_MODE => true,
                ],
            ],
        );

        $this->assertSame($expectedDeliveryExecution, $deliveryExecution);
    }

    public function testItCreateNewDeliveryExecutionIfUserProvidedButDeliveryExecutionNotFound(): void
    {
        $delivery = $this->createTestDelivery();
        $this->repository
            ->method('find')
            ->willThrowException(new DocumentNotFoundException());

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            [
                'user_id' => 'userId',
            ],
        );

        $this->assertEquals($deliveryExecution->getTenantId(), $delivery->getTenantId());
        $this->assertEquals($deliveryExecution->getLtiLaunchParameters()['user_id'], 'userId');
    }

    public function testItCanCreateDeliveryExecutionWithCloseAt(): void
    {
        $delivery = $this->createTestDelivery();
        $date = Carbon::createFromFormat(
            DateTimeInterface::RFC3339,
            '2021-01-01T00:00:00Z',
        );

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParametersWithClosureClaims(),
        );

        $this->assertEquals($date, $deliveryExecution->getCloseAt());
    }

    public function testItDoesntResumeOnDryRun(): void
    {
        $delivery = $this->createTestDelivery();
        $expectedDeliveryExecution = $this->createTestDeliveryExecution();
        $this->repository
            ->expects($this->once())
            ->method('find')
            ->willReturn($expectedDeliveryExecution);

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            [
                'user_id' => 'userId',
                'custom' => [
                    LtiCustomSettings::PARAM_DRY_RUN => false,
                ],
            ],
        );

        $this->assertSame($expectedDeliveryExecution, $deliveryExecution);

        $deliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            [
                'user_id' => 'userId',
                'custom' => [
                    LtiCustomSettings::PARAM_DRY_RUN => true,
                ],
            ],
        );

        $this->assertNotSame($expectedDeliveryExecution, $deliveryExecution);
    }

    /**
     * @dataProvider getDataForCreationDeliveryExecutionId
     */
    public function testItCanCreateDeliveryExecutionId(Delivery $delivery, $parameters, $expectedId): void
    {
        $deliveryExecutionId = $this->createSubject()->createDeliveryExecutionId(
            $delivery->getId(),
            $delivery->getTenantId(),
            $parameters,
        );

        $this->assertEquals($expectedId, $deliveryExecutionId);
    }

    public function testItOverrideDeliveryExecutionClaimSet(): void
    {
        $launchParams = [
            'lti_version' => LtiMessageInterface::LTI_VERSION,
            'user_name' => "userName",
            'user_locale' => "userLocale",
            'launch_presentation_return_url' => "returnUrl",
            'custom' => [
                LtiCustomSettings::PARAM_FORCE_RESUME => true,
                LtiCustomSettings::PARAM_ENABLE_MONITORING => true,
            ],
        ];
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createTestDeliveryExecution(ltiLaunchParameters: $launchParams);
        $launchParams['launch_presentation_return_url'] = 'anotherReturnUrl';
        $launchParams['custom'] = [
            LtiCustomSettings::PARAM_DRY_RUN => true,
        ];
        $actualDeliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $launchParams,
        );

        $this->assertEquals(
            $launchParams['launch_presentation_return_url'],
            $actualDeliveryExecution->getLtiLaunchParameters()['launch_presentation_return_url'],
        );
        $this->assertSame(
            $launchParams['custom'],
            $actualDeliveryExecution->getLtiLaunchParameters()['custom'],
        );

        $this->assertNotEquals(
            $deliveryExecution->getLtiLaunchParameters()['launch_presentation_return_url'],
            $actualDeliveryExecution->getLtiLaunchParameters()['launch_presentation_return_url'],
        );
        $this->assertNotSame(
            $deliveryExecution->getLtiLaunchParameters()['custom'],
            $actualDeliveryExecution->getLtiLaunchParameters()['custom'],
        );
    }

    public function testGetDeliveryExecutionWithReset(): void
    {
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->repository->expects($this->once())->method('find')->willReturn($deliveryExecution);
        $this->deliveryExecutionDeleterMock->expects($this->once())->method('deleteRelatedEntities')->with($deliveryExecution);

        $actualDeliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParameterWithResetClaim(),
        );

        $this->assertNotEquals(spl_object_id($deliveryExecution), spl_object_id($actualDeliveryExecution));
    }

    public function testGetNotFinishedDeliveryExecutionAttemptLimit(): void
    {
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->repository->expects($this->once())->method('find')->willReturn($deliveryExecution);
        $this->repository->expects($this->never())->method('delete');
        $this->aliasRepository->expects($this->never())->method('deleteDeliveryExecutionId');

        $actualDeliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParameterWithAttemptLimit(),
        );

        $this->assertEquals(spl_object_id($deliveryExecution), spl_object_id($actualDeliveryExecution));
    }

    public function testGetFinishedDeliveryExecutionAttemptLimit(): void
    {
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createTestDeliveryExecution(status: DeliveryExecution::STATUS_CLOSED);

        $this->repository->expects($this->once())->method('find')->willReturn($deliveryExecution);

        $actualDeliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParameterWithAttemptLimit(),
        );

        $this->assertNotEquals(spl_object_id($deliveryExecution), spl_object_id($actualDeliveryExecution));
    }

    public function testGetDeliveryExecutionAttemptLimitHasPriorityOverReset(): void
    {
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->repository->expects($this->once())->method('find')->willReturn($deliveryExecution);
        $this->repository->expects($this->never())->method('delete');
        $this->aliasRepository->expects($this->never())->method('deleteDeliveryExecutionId');

        $actualDeliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParameterWithAttemptLimitAndReset(),
        );

        $this->assertEquals(spl_object_id($deliveryExecution), spl_object_id($actualDeliveryExecution));
    }

    public function testGetDeliveryExecutionAttemptLimitHasPriorityOverForceResume(): void
    {
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createTestDeliveryExecution(status: DeliveryExecution::STATUS_CLOSED);

        $this->repository->expects($this->once())->method('find')->willReturn($deliveryExecution);

        $actualDeliveryExecution = $this->createSubject()->getDeliveryExecution(
            $delivery,
            $this->getLtiParameterWithAttemptLimitAndForceResume(),
        );

        $this->assertNotEquals(spl_object_id($deliveryExecution), spl_object_id($actualDeliveryExecution));
        $this->assertNotEquals($deliveryExecution->getStartedAt(), $actualDeliveryExecution->getStartedAt());
        $this->assertEquals(
            $deliveryExecution->getStartedAt()->getTimestamp(),
            $actualDeliveryExecution->getInitialStartTimestamp(),
        );
    }

    public function getDataForCreationDeliveryExecutionId(): array
    {
        return [
            [
                $this->createTestDelivery('deliveryId'),
                [
                    'result_id' => 'resultId',
                    'user_id' => 'userId',
                ],
                'dIresu#deliveryId#0a92fab3230134cca6eadd9898325b9b2ae67998#1',
            ],
            [
                $this->createTestDelivery('deliveryId'),
                [
                    'result_id' => 'resultId',
                    'user_id' => 'userId',
                    'custom' => [
                        'deliverySettings.dryRun' => 'true',
                    ],
                ],
                'dIresu#deliveryId#504a3bd1d38b79d8e78a027b1848cd7a58c04481#1',
            ],
            [
                $this->createTestDelivery('deliveryId'),
                [
                    'result_id' => 'resultId',
                    'user_id' => 'http://backoffice.docker.localhost/ontologies/tao.rdf#i61483c564e04579274cdbcf106ace4',
                ],
                '4eca601fcbdc47297540e465c38416i32%fdr.oatF2%seigolotnoF2%tsohlacol.rekcod.eciffokcabF2%F2%A3%ptth#deliveryId#0a92fab3230134cca6eadd9898325b9b2ae67998#1',
            ],
        ];
    }

    public function testItKeepsContextIdEmptyIfNotProvided(): void
    {
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createSubject()->getDeliveryExecution($delivery, []);

        $this->assertNull($deliveryExecution->getLtiLaunchParameters()['context_id']);
        $this->assertArrayNotHasKey('is_context_inherited', $deliveryExecution->getLtiLaunchParameters());
    }

    public function testItKeepsContextIdEmptyIfEmptyContextIdProvided(): void
    {
        $delivery = $this->createTestDelivery(
            configuration: ['metadata' => ['context-id' => ['']]],
        );
        $deliveryExecution = $this->createSubject()->getDeliveryExecution($delivery, []);

        $this->assertNull($deliveryExecution->getLtiLaunchParameters()['context_id']);
        $this->assertArrayNotHasKey('is_context_inherited', $deliveryExecution->getLtiLaunchParameters());
    }

    public function testItSetsContextIdFromDeliveryMetadata(): void
    {
        $contextId = 'test-context-id';
        $delivery = $this->createTestDelivery(
            configuration: ['metadata' => ['context-id' => [$contextId]]],
        );
        $deliveryExecution = $this->createSubject()->getDeliveryExecution($delivery, []);

        $this->assertSame($contextId, $deliveryExecution->getLtiLaunchParameters()['context_id']);
        $this->assertTrue($deliveryExecution->getLtiLaunchParameters()['is_context_inherited']);
    }

    public function testSetLocaleForDeliveryExecutionSuccessfully(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $deliveryExecution->setLocale(null);

        $delivery = $this->createMock(Delivery::class);
        $delivery->method('isSupportedLocale')->with('en-US')->willReturn(true);
        $delivery->method('getCreatedAt')->willReturn(Carbon::now());

        $this->createSubject()->setLocaleForDeliveryExecution($delivery, $deliveryExecution, 'en-US');
        $this->assertEquals('en-US', $deliveryExecution->getLocale());
    }

    public function testSetLocaleForDeliveryExecutionWhenAlreadySet(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $deliveryExecution->setLocale('en-US');
        $deliveryExecution->setStatus(DeliveryExecutionStatus::STATUS_INTERACTING->value);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Locale has already been set and cannot be overridden.');

        $this->createSubject()->setLocaleForDeliveryExecution(
            $this->createMock(Delivery::class),
            $deliveryExecution,
            'fr-FR',
        );
    }

    public function testSetLocaleForDeliveryExecutionWithUnsupportedLocale(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $deliveryExecution->setLocale(null);

        $delivery = $this->createMock(Delivery::class);
        $delivery->method('isSupportedLocale')->with('en-GB')->willReturn(false);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Selected locale is not supported by this delivery.');

        $this->createSubject()->setLocaleForDeliveryExecution($delivery, $deliveryExecution, 'en-GB');
    }

    public function testCreateDeliveryExecutionWithLocale(): void
    {
        $parameters = [
            'result_id' => 'resultId',
        ];
        $deliveryExecutionId = 'userId#deliveryId#attemptId#tenantId';
        $delivery = $this->createTestDelivery();
        $deliveryExecution = $this->createSubject()->createDeliveryExecution(
            $delivery,
            $deliveryExecutionId,
            $parameters,
            locale: 'en-US',
        );

        $this->assertEquals('en-US', $deliveryExecution->getLocale());
    }

    private function getLtiParametersForReview(): array
    {
        return [
            'user_id' => 'userId',
            'custom' => [
                LtiCustomSettings::PARAM_REVIEW_MODE => true,
            ],
        ];
    }

    private function getLtiParametersWithClosureClaims(): array
    {
        return [
            'custom' => [
                LtiCustomSettings::PARAM_CLOSE_ON => '2021-01-01T00:00:00Z',
            ],
        ];
    }

    private function getLtiParameterWithResetClaim(): array
    {
        return [
            'user_id' => 'userId',
            'custom' => [
                LtiCustomSettings::PARAM_RESET => 'true',
            ],
        ];
    }

    private function getLtiParameterWithAttemptLimit(): array
    {
        return [
            'user_id' => 'userId',
            'custom' => [
                LtiCustomSettings::PARAM_ATTEMPT_LIMIT => '0',
            ],
        ];
    }

    private function getLtiParameterWithAttemptLimitAndReset(): array
    {
        return [
            'user_id' => 'userId',
            'custom' => [
                LtiCustomSettings::PARAM_ATTEMPT_LIMIT => '0',
                LtiCustomSettings::PARAM_RESET => 'true',
            ],
        ];
    }

    private function getLtiParameterWithAttemptLimitAndForceResume(): array
    {
        return [
            'user_id' => 'userId',
            'custom' => [
                LtiCustomSettings::PARAM_ATTEMPT_LIMIT => '0',
                LtiCustomSettings::PARAM_FORCE_RESUME => 'true',
            ],
        ];
    }

    private function createSubject(): DeliveryExecutionService
    {
        return new DeliveryExecutionService(
            $this->repository,
            new UuidGenerator(),
            new LtiCustomSettings($this->getContainer()->get(LtiTokenResolver::class)),
            $this->createMock(EventDispatcherInterface::class),
            $this->deliveryExecutionDeleterMock,
            $this->createMock(LockFactory::class),
            $this->aliasRepository,
            $this->testSessionInitiator,
        );
    }
}
