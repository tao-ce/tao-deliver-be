<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Validator\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Validator\DeliveryExecution\KioskSettingsValidator;
use OAT\Library\EnvironmentManagementClient\Model\Configuration;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KioskSettingsValidatorTest extends KernelTestCase
{
    public const TENANT_ID = 'tenant-id';
    public const CONFIGURATION_NAME = 'portal.secure_browser_settings';

    private DeliveryExecution $deliveryExecution;
    private ConfigurationRepositoryInterface&MockObject $configurationRepository;
    private KioskSettingsValidator $sut;

    /**
     * @before
     */
    public function init(): void
    {
        $this->deliveryExecution = $this->createMock(DeliveryExecution::class);
        $this->deliveryExecution->method('getTenantId')->willReturn(self::TENANT_ID);
        $this->sut = new KioskSettingsValidator(
            static::getContainer()->get('validator'),
            $this->configurationRepository = $this->createMock(ConfigurationRepositoryInterface::class),
        );
        $this->sut->setDeliveryExecution($this->deliveryExecution);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testValidation(array $expected = [], ?Configuration $configuration = null): void
    {
        $this->configurationRepository
            ->method('find')
            ->with(self::TENANT_ID, self::CONFIGURATION_NAME)
            ->willReturn($configuration);

        $this->assertEquals(
            $expected,
            $this->sut->getValidatedRequestParameters(),
        );
    }

    public function dataProvider(): array
    {
        return [
            'null configuration' => [
                'expected' => ['enabled' => false],
            ],
            'malformed configuration' => [
                'expected' => ['enabled' => false],
                'configuration' => new Configuration(self::CONFIGURATION_NAME, 'malformed data'),
            ],
            'missing minimum version' => [
                'expected' => ['enabled' => true, 'minVersion' => '0.0.0'],
                'configuration' => new Configuration(self::CONFIGURATION_NAME, ['enabled' => true]),
            ],
            'malformed process deny list' => [
                'expected' => ['enabled' => false],
                'configuration' => new Configuration(
                    self::CONFIGURATION_NAME,
                    [
                        'enabled' => true,
                        'minimumVersion' => '1.7.1',
                        'processDenyList' => [['name' => 'process_1', 'label' => 'Process 1'], ['malformed data']],
                    ],
                ),
            ],
            'missing process deny name' => [
                'expected' => ['enabled' => false],
                'configuration' => new Configuration(
                    self::CONFIGURATION_NAME,
                    [
                        'enabled' => true,
                        'minimumVersion' => '1.7.1',
                        'processDenyList' => [
                            ['name' => 'process_1', 'label' => 'Process 1'],
                            ['label' => 'Process 2'],
                        ],
                    ],
                ),
            ],
            'missing process deny label' => [
                'expected' => ['enabled' => false],
                'configuration' => new Configuration(
                    self::CONFIGURATION_NAME,
                    [
                        'enabled' => true,
                        'minimumVersion' => '1.7.1',
                        'processDenyList' => [
                            ['name' => 'process_1', 'label' => 'Process 1'],
                            ['name' => 'process_2'],
                        ],
                    ],
                ),
            ],
            'empty process deny list' => [
                'expected' => [
                    'enabled' => true,
                    'minVersion' => '1.7.1',
                    'downloads' => [
                        'mac' => [
                            'url' => 'https://example.com/mac.dmg',
                        ],
                        'windows' => [
                            'url' => 'https://example.com/windows.exe',
                        ],
                    ],
                ],
                'configuration' => new Configuration(
                    self::CONFIGURATION_NAME,
                    [
                        'enabled' => true,
                        'minimumVersion' => '1.7.1',
                        'processDenyList' => [],
                        'downloads' => [
                            'mac' => [
                                'url' => 'https://example.com/mac.dmg',
                            ],
                            'windows' => [
                                'url' => 'https://example.com/windows.exe',
                            ],
                        ],
                    ],
                ),
            ],
            'extra fields' => [
                'expected' => [
                    'enabled' => true,
                    'extraField' => 'extra value',
                    'minVersion' => '1.7.1',
                    'downloads' => [
                        'mac' => [
                            'url' => 'https://example.com/mac.dmg',
                            'description' => 'Mac OS X',
                        ],
                        'windows' => [
                            'url' => 'https://example.com/windows.exe',
                            'description' => 'Windows',
                        ],
                    ],
                ],
                'configuration' => new Configuration(
                    self::CONFIGURATION_NAME,
                    [
                        'enabled' => true,
                        'extraField' => 'extra value',
                        'minimumVersion' => '1.7.1',
                        'processDenyList' => [],
                        'downloads' => [
                            'mac' => [
                                'url' => 'https://example.com/mac.dmg',
                                'description' => 'Mac OS X',
                            ],
                            'windows' => [
                                'url' => 'https://example.com/windows.exe',
                                'description' => 'Windows',
                            ],
                        ],
                    ],
                ),
            ],
        ];
    }
}
