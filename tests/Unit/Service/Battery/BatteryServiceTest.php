<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Battery;

use App\Domain\Battery\Exception\EmptyBatteryException;
use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\Delivery\Model\Delivery;
use App\Lti\LtiCustomSettings;
use App\Repository\BatteryDistributionRepository;
use App\Repository\BatteryRepository;
use App\Repository\DeliveryRepository;
use App\Service\Battery\BatteryService;
use App\Service\BatteryDistribution\BatteryDeliveryToExecuteRetriever;
use App\Tests\Traits\DomainTestingTrait;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WeakMap;

class BatteryServiceTest extends TestCase
{
    use DomainTestingTrait;

    private readonly BatteryRepository $batteryRepository;
    private readonly BatteryDistributionRepository $batteryDistributionRepository;
    private readonly BatteryDeliveryToExecuteRetriever $batteryDeliveryToExecuteRetriever;
    private readonly DeliveryRepository $deliveryRepository;
    private readonly LtiCustomSettings $ltiCustomSettings;
    private readonly BatteryService $sut;

    protected function setUp(): void
    {
        $this->batteryRepository = $this->createMock(BatteryRepository::class);
        $this->batteryDistributionRepository = $this->createMock(BatteryDistributionRepository::class);
        $this->batteryDeliveryToExecuteRetriever = $this->createMock(BatteryDeliveryToExecuteRetriever::class);
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->ltiCustomSettings = $this->createMock(LtiCustomSettings::class);

        $this->sut = new BatteryService(
            $this->batteryRepository,
            $this->batteryDistributionRepository,
            $this->batteryDeliveryToExecuteRetriever,
            $this->deliveryRepository,
            $this->ltiCustomSettings,
        );
    }

    public function testFindBattery(): void
    {
        $battery = $this->createMock(Battery::class);

        $this->batteryRepository
            ->expects($this->once())
            ->method('find')
            ->with('batteryId')
            ->willReturn($battery);

        $this->assertEquals($battery, $this->sut->findBatteryOrFail('batteryId'));
    }

    public function testFindBatteryFail(): void
    {
        $this->batteryRepository
            ->expects($this->once())
            ->method('find')
            ->with('batteryId')
            ->willThrowException(new DocumentNotFoundException());

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('[IRRECOVERABLE] Battery with id batteryId not found');

        $this->sut->findBatteryOrFail('batteryId');
    }

    public function testGetAssignedDeliveryNewDistribution(): void
    {
        $delivery = $this->createBatteryDelivery('deliveryId');
        $battery = $this->createBattery([$delivery]);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->willReturn($delivery);

        $batteryDistribution = $this->createBatteryDistribution($battery);
        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($battery, 'userId')
            ->willReturn($batteryDistribution);

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $battery);

        $this->assertEquals($delivery, $this->sut->getAssignedDelivery($battery, ['user_id' => 'userId']));
    }

    public function testGetAssignedDeliveryExistingDistribution(): void
    {
        $delivery = $this->createBatteryDelivery('deliveryId');
        $battery = $this->createBattery([$delivery]);
        $batteryDistribution = $this->createBatteryDistribution($battery);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($battery, 'userId')
            ->willReturn($batteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->with($batteryDistribution)
            ->willReturn($delivery);

        $this->batteryDistributionRepository
            ->expects($this->never())
            ->method('save');

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $battery);

        $this->assertEquals($delivery, $this->sut->getAssignedDelivery($battery, ['user_id' => 'userId']));
    }

    public function testGetAssignedDeliveryInReviewModeWithExistingDistribution(): void
    {
        $this->ltiCustomSettings->method('isReviewModeEnabled')->willReturn(true);
        $delivery = $this->createBatteryDelivery('deliveryId');
        $battery = $this->createBattery([$delivery]);
        $existingBatteryDistribution = $this->createBatteryDistribution($battery);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->never())
            ->method('filter');
        $this->batteryDistributionRepository
            ->expects($this->never())
            ->method('findOrCreate');
        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->with($battery->getId(), 'userId')
            ->willReturn($existingBatteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->never())
            ->method('retrieve');

        $this->assertEquals($delivery, $this->sut->getAssignedDelivery($battery, ['user_id' => 'userId']));
    }

    public function testGetAssignedDeliveryUpdatedBattery(): void
    {
        $delivery = $this->createBatteryDelivery('deliveryId');
        $deliverySnapshot = $this->createBatteryDelivery('oldDeliveryId');
        $battery = $this->createBattery([$delivery]);
        $batterySnapshot = $this->createBattery([$deliverySnapshot]);
        $existingBatteryDistribution = $this->createBatteryDistribution($batterySnapshot);

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $battery);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($battery, 'userId')
            ->willReturn($existingBatteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->willReturn($delivery);

        $this->assertNotEmpty($this->sut->getAssignedDelivery($battery, ['user_id' => 'userId']));
    }

    public function testGetAssignedDeliveryNoDeliveriesButDistributionExists(): void
    {
        $batteryWithoutDeliveries = $this->createBattery();

        $deliverySnapshot = $this->createBatteryDelivery('deliveryId');
        $batterySnapshot = $this->createBattery([$deliverySnapshot]);
        $existingBatteryDistribution = $this->createBatteryDistribution($batterySnapshot);

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $batteryWithoutDeliveries);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($batteryWithoutDeliveries, 'userId')
            ->willReturn($existingBatteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->willReturn($deliverySnapshot);

        $this->assertNotEmpty(
            $this->sut->getAssignedDelivery($batteryWithoutDeliveries, ['user_id' => 'userId']),
        );
    }

    public function testGetAssignedDeliveryNoDeliveriesAndDistributionNotExists(): void
    {
        $batteryWithoutDeliveries = $this->createBattery();

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $batteryWithoutDeliveries);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($batteryWithoutDeliveries, 'userId')
            ->willThrowException(new EmptyBatteryException());

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->never())
            ->method('retrieve');

        $this->batteryDistributionRepository
            ->expects($this->never())
            ->method('save');

        $this->expectException(EmptyBatteryException::class);
        $this->sut->getAssignedDelivery($batteryWithoutDeliveries, ['user_id' => 'userId']);
    }

    public function testGetAssignedDeliveryExistingDistributionWithLocale(): void
    {
        $delivery = $this->createBatteryDelivery('deliveryId');
        $battery = $this->createBattery([$delivery]);
        $batteryDistribution = $this->createBatteryDistribution($battery);
        $batteryDistribution->setLocale('en-US');

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($battery, 'userId')
            ->willReturn($batteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->with($batteryDistribution, ['user_id' => 'userId', 'locale' => 'en-US'])
            ->willReturn($delivery);

        $this->batteryDistributionRepository
            ->expects($this->never())
            ->method('save');

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $battery);

        $ltiLaunchParameters = [
            'user_id' => 'userId',
            'locale' => 'en-US',
        ];

        $this->assertEquals($delivery, $this->sut->getAssignedDelivery($battery, $ltiLaunchParameters));
    }

    public function testGetAssignedDeliveryExistingDistributionWithoutLocale(): void
    {
        $delivery = $this->createBatteryDelivery('deliveryId');
        $battery = $this->createBattery([$delivery]);
        $batteryDistribution = $this->createBatteryDistribution($battery);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($battery, 'userId')
            ->willReturn($batteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->with($batteryDistribution, ['user_id' => 'userId'])
            ->willReturn($delivery);

        $this->batteryDistributionRepository
            ->expects($this->never())
            ->method('save');

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $battery);

        $ltiLaunchParameters = ['user_id' => 'userId'];

        $result = $this->sut->getAssignedDelivery($battery, $ltiLaunchParameters);

        $this->assertEquals($delivery, $result);
        $this->assertArrayNotHasKey('locale', $ltiLaunchParameters);
    }

    public function testGetAssignedDeliveryNewDistributionWithoutLocale(): void
    {
        $delivery = $this->createBatteryDelivery('deliveryId');
        $battery = $this->createBattery([$delivery]);

        $batteryDistribution = $this->createBatteryDistribution($battery);
        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findOrCreate')
            ->with($battery, 'userId')
            ->willReturn($batteryDistribution);

        $this->batteryDeliveryToExecuteRetriever
            ->expects($this->once())
            ->method('retrieve')
            ->willReturn($delivery)
        ;

        $this->batteryDeliveryToExecuteRetriever->method('filter')->willReturn(clone $battery);

        $ltiLaunchParameters = ['user_id' => 'userId'];

        $result = $this->sut->getAssignedDelivery($battery, $ltiLaunchParameters);

        $this->assertEquals($delivery, $result);
        $this->assertArrayNotHasKey('locale', $ltiLaunchParameters);
    }

    public function testGetCommonLocalesWithCommonLocales(): void
    {
        $batteryDelivery1 = $this->createBatteryDelivery('deliveryId1');
        $batteryDelivery2 = $this->createBatteryDelivery('deliveryId2');

        $battery = $this->createBattery([$batteryDelivery1, $batteryDelivery2]);

        $delivery1 = $this->createDelivery(
            id: 'deliveryId1',
            supportedLocales: ['en_US', 'fr_FR', 'de_DE'],
        );

        $delivery2 = $this->createDelivery(
            id: 'deliveryId2',
            supportedLocales: ['en_US', 'fr_FR'],
        );

        $this->deliveryRepository
            ->method('find')
            ->willReturnMap([
                ['deliveryId1', $delivery1],
                ['deliveryId2', $delivery2],
            ]);

        $commonLocales = $this->sut->getCommonLocales($battery);

        $this->assertEquals(['en_US', 'fr_FR'], $commonLocales);
    }

    public function testGetCommonLocalesWithPartialCommonLocales(): void
    {
        $batteryDelivery1 = $this->createBatteryDelivery('deliveryId1');
        $batteryDelivery2 = $this->createBatteryDelivery('deliveryId2');

        $battery = $this->createBattery([$batteryDelivery1, $batteryDelivery2]);

        $delivery1 = $this->createDelivery(
            id: 'deliveryId1',
            supportedLocales: ['en_US', 'fr_FR', 'de_DE'],
        );

        $delivery2 = $this->createDelivery(
            id: 'deliveryId2',
            supportedLocales: [],
        );

        $this->deliveryRepository
            ->method('find')
            ->willReturnMap([
                ['deliveryId1', $delivery1],
                ['deliveryId2', $delivery2],
            ]);

        $this->assertEmpty($this->sut->getCommonLocales($battery));
    }

    private function createBatteryDelivery(string $id): BatteryDelivery
    {
        return new BatteryDelivery($id, null, null, null, null);
    }

    /**
     * @param BatteryDelivery[] $deliveries
     */
    private function createBattery(array $deliveries = []): Battery
    {
        return new Battery(
            'batteryId',
            'batteryTenantId',
            'batteryName',
            deliveries: $deliveries,
        );
    }

    private function createBatteryDistribution(Battery $battery): BatteryDistribution
    {
        return new BatteryDistribution(
            'batteryDistributionId',
            'userId',
            $battery,
        );
    }

    private function createDelivery(string $id, array $supportedLocales): Delivery
    {
        return $this->createTestDelivery(
            id: $id,
            supportedLocales: $supportedLocales,
        );
    }

    public function testIsMultiLanguageBatteryReturnsTrueWhenAllDeliveriesAreMultiLanguage(): void
    {
        $battery = $this->createTestBattery(
            deliveries: [],
        );

        $this->setDeliveries(
            $battery,
            [
                'deliveryId' => $this->createTestDelivery(id: 'deliveryId', supportedLocales: ['en-US', 'fr-FR']),
                'deliveryId2' => $this->createTestDelivery(id: 'deliveryId2', supportedLocales: ['en-US', 'es-ES']),
            ],
        );

        $this->assertTrue($this->sut->isMultiLanguageBattery($battery));
    }

    public function testIsMultiLanguageBatteryReturnsFalseWhenAtLeastOneDeliveryIsNotMultiLanguage(): void
    {
        $battery = $this->createTestBattery();

        $this->setDeliveries(
            $battery,
            [
                'deliveryId' => $this->createTestDelivery(id: 'deliveryId', supportedLocales: ['en-US', 'fr-FR']),
                'deliveryId2' => $this->createTestDelivery(id: 'deliveryId2', supportedLocales: ['en-US']),
                'deliveryId3' => $this->createTestDelivery(id: 'deliveryId3', supportedLocales: ['en-US', 'fr-FR', 'es-ES']),
            ],
        );

        $this->assertFalse($this->sut->isMultiLanguageBattery($battery));
    }

    public function testIsMultiLanguageBatteryReturnsFalseWhenNoDeliveriesExist(): void
    {
        $battery = $this->createTestBattery();
        $this->setDeliveries($battery, []);

        $this->assertFalse($this->sut->isMultiLanguageBattery($battery));
    }

    private function setDeliveries(Battery $battery, array $deliveries): void
    {
        $reflection = new ReflectionClass($this->sut);
        $property = $reflection->getProperty('deliveries');

        /** @var WeakMap $deliveryMap */
        $deliveryMap = $property->getValue($this->sut);
        foreach ($deliveries as $delivery) {
            $deliveryMap[$battery] ??= [];
            $deliveryMap[$battery][$delivery->getId()] = $delivery;
        }

        $property->setValue($this->sut, $deliveryMap);
    }
}
