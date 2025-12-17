<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;
use App\Domain\Publication\Model\Publication;
use Carbon\Carbon;
use OAT\Library\TenantManagement\Model\Credentials;
use OAT\Library\TenantManagement\Model\Lti1p3Credentials;
use OAT\Library\TenantManagement\Model\Tenant;
use OAT\Library\TenantManagement\Model\TenantInterface;
use OAT\Library\TenantManagement\Model\TestRunnerThemeInterface;
use DateTimeInterface;

trait DomainTestingTrait
{
    protected function createTestPublication(
        string $status = Publication::STATUS_CREATED,
        string $id = 'id',
        string $tenantId = 'tenantId',
        string $packagePath = 'path/to/package',
        string $packageRef = '',
        array $packageConfiguration = ['configurationKey' => 'configurationValue'],
        array $reports = [],
        ?string $deliveryId = null,
        ?string $locale = null,
    ): Publication {
        return new Publication(
            $id,
            $tenantId,
            $packagePath,
            $packageRef,
            $packageConfiguration,
            $reports,
            $status,
            $deliveryId,
            $locale,
        );
    }

    protected function createTestDelivery(
        string $id = 'id',
        string $tenantId = '1',
        string $compactTestFilePath = 'compactTestFilePath',
        array $configuration = ['property' => 'value'],
        array $qtiItemsMapping = ['assessmentItemRefId' => ['item information']],
        ?string $packageRef = null,
        bool $isDeleted = false,
        ?string $mainLocale = null,
        array $supportedLocales = [],
        ?DateTimeInterface $createdAt = null,
    ): Delivery {

        return new Delivery(
            $id,
            $tenantId,
            $createdAt ?? Carbon::now(),
            $compactTestFilePath,
            $configuration,
            $qtiItemsMapping,
            $packageRef,
            $isDeleted,
            mainLocale: $mainLocale,
            supportedLocales: $supportedLocales,
        );
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
        ?DateTimeInterface $updatedAt = null,
        ?string $locale = null,
    ): DeliveryExecution {
        if (!isset($ltiLaunchParameters['result_id'])) {
            $ltiLaunchParameters['result_id'] = 'lisResultSourcedId';
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
            $updatedAt,
            locale: $locale,
        );
    }

    protected function createTestServerDuration(
        string $qtiComponentIdentifier = 'qtiComponentIdentifier',
        float $startedAt = 12.34,
        ?float $endedAt = null,
    ): ServerDuration {
        return new ServerDuration($qtiComponentIdentifier, $startedAt, $endedAt);
    }

    protected function createTestTenant(
        string $id = 'tenantId',
        string $customerId = 'customerId',
        string $audience = 'audience',
        string $label = 'label',
        string $serviceType = 'serviceType',
        array $serviceUrls = ['serviceUrl'],
        ?array $lti1p0Credentials = null,
        ?array $lti1p3Credentials = null,
        ?array $oAuth2Credentials = null,
        ?array $preferences = null,
        ?TestRunnerThemeInterface $testRunnerTheme = null,
        ?array $testRunnerConfiguration = null,
    ): Tenant {
        return new Tenant(
            $id,
            $customerId,
            $audience,
            $label,
            $serviceType,
            $serviceUrls,
            $lti1p0Credentials,
            $lti1p3Credentials,
            $oAuth2Credentials,
            $preferences,
            $testRunnerTheme,
            $testRunnerConfiguration,
        );
    }

    /**
     * @param BatteryDelivery[] $deliveries
     */
    protected function createTestBattery(
        string $status = 'status',
        string $mode = Battery::MODE_RANDOM_DELIVERY,
        string $id = 'batteryId',
        string $tenantId = 'tenantId',
        string $name = 'name',
        string $description = '',
        ?array $deliveries = null,
    ): Battery {
        return new Battery(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            status: $status,
            mode: $mode,
            deliveries: $deliveries ?? [$this->createTestBatteryDelivery()],
        );
    }

    protected function createTestBatteryDelivery(
        string $id = 'deliveryId',
        ?string $password = null,
        ?string $order = null,
    ): BatteryDelivery {
        return new BatteryDelivery(
            id: $id,
            password: $password,
            order: $order,
            startDateValidation: 1726759110,
            endDateValidation: 1726759110,
        );
    }

    protected function createTestBatteryDistribution(
        ?Battery $battery = null,
        string $id = 'dIresu#batteryId',
        string $userId = 'userId',
    ): BatteryDistribution {
        return new BatteryDistribution(
            id: $id,
            userId: $userId,
            battery: $battery ?? $this->createTestBattery(),
        );
    }

    protected function createTestTenantWithCredentials(): TenantInterface
    {
        return $this->createTestTenant(
            'id',
            'customerId',
            'audience',
            'label',
            'serviceType',
            ['serviceUrl'],
            [
                new Credentials('ltiKey', 'secret', ['role']),
            ],
            [
                new Lti1p3Credentials('clientId', 'jwksUrl', 'publicKey'),
            ],
            [
                new Credentials('oAuth2Key', 'secret', ['role']),
            ],
        );
    }
}
