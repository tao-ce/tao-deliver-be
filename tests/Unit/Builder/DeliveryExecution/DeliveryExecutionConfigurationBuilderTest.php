<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Builder\DeliveryExecution;

use App\Builder\DeliveryExecution\DeliveryExecutionConfigurationBuilder;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionConfigurationBuilderTest extends TestCase
{
    private const PLUGIN_PROVIDER_DUMMY = [
        'id' => 'DummyPlugin',
        'category' => 'tools',
        'module' => 'taoQtiNuiTest/runner/plugins/security/dummyPlugin/plugin',
    ];

    public function testBuildWithInitialConfiguration(): void
    {
        $subject = new DeliveryExecutionConfigurationBuilder(['key1' => 'value1']);

        $this->assertEquals(['key1' => 'value1'], $subject->build());
    }

    public function testBuildWithNoInitialConfiguration(): void
    {
        $subject = new DeliveryExecutionConfigurationBuilder([]);

        $this->assertEquals([], $subject->build());
    }

    public function testWithSettingsPlugin(): void
    {
        $subject = new DeliveryExecutionConfigurationBuilder([]);

        $this->assertSame(
            [
                'providers' => [
                    'plugins' => [
                        [
                            'category' => 'plugins',
                            'id' => 'pluginsSettings',
                            'module' => 'taoQtiNuiTest/runner/plugins/settings/plugin',
                        ],
                    ],
                ],
            ],
            $subject->withSettingsPlugin()->build(),
        );
    }

    /**
     * @dataProvider withForceFullscreenPluginDataProvider
     */
    public function testWithForceFullscreenPlugin(
        array $expected,
        array $initialConfiguration,
        bool $autoresume,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withForceFullScreenPlugin($autoresume));
        $this->assertEquals($expected, $subject->build());
    }

    public function withForceFullscreenPluginDataProvider(): array
    {
        return [
            'Having autoresume enabled' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'forceFullscreen' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
                'autoresume' => true,
            ],
            'Not having autoresume enabled' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'forceFullscreen' => [
                                'autoresume' => false,
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
                'autoresume' => false,
            ],
        ];
    }

    /**
     * @dataProvider withoutForceFullscreenPluginDataProvider
     */
    public function testWithoutForceFullscreenPlugin(
        array $expected,
        array $initialConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withoutForceFullScreenPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    public function withoutForceFullscreenPluginDataProvider(): array
    {
        return [
            'initially having forceFullscreen' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [],
                    ],
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'forceFullscreen' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'initially not having forceFullscreen provider nor options' => [
                'expected' => [
                    'key1' => 'value1',
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
            ],
            'initially not having forceFullscreen provider BUT having options' => [
                'expected' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [
                            'forceFullscreen' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'configuration for other plugins is preserved' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'forceFullscreen' => [
                                'autoresume' => false,
                            ],
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider withoutFullscreenPluginDataProvider
     */
    public function testWithoutFullscreenPlugin(
        array $expected,
        array $initialConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withoutFullScreenPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    public function withoutFullscreenPluginDataProvider(): array
    {
        return [
            'initially having fullscreen' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FULLSCREEN],
                    ],
                ],
            ],
        ];
    }

    public function testWithRefreshPlugin(): void
    {
        $expected = [
            'key1' => 'value1',
            'providers' => [
                'plugins' => [
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_REFRESH,
                ],
            ],
        ];

        $subject = new DeliveryExecutionConfigurationBuilder(['key1' => 'value1']);

        $this->assertSame($subject, $subject->withRefreshPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    /**
     * @dataProvider withPauseOnBlurPluginDataProvider
     */
    public function testWithPauseOnBlurPlugin(
        array $expected,
        array $initialConfiguration,
        bool $autoresume,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withPauseOnBlurPlugin($autoresume));
        $this->assertEquals($expected, $subject->build());
    }

    public function withPauseOnBlurPluginDataProvider(): array
    {
        return [
            'Having autoresume enabled' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'pauseOnBlur' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
                'autoresume' => true,
            ],
            'Not having autoresume enabled' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'pauseOnBlur' => [
                                'autoresume' => false,
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
                'autoresume' => false,
            ],
        ];
    }

    /**
     * @dataProvider withoutPauseOnBlurPluginDataProvider
     */
    public function testWithoutPauseOnBlurPlugin(
        array $expected,
        array $initialConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withoutPauseOnBlurPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    public function withoutPauseOnBlurPluginDataProvider(): array
    {
        return [
            'initially having pauseOnBlur' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [],
                    ],
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'pauseOnBlur' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'initially not having pauseOnBlur provider nor options' => [
                'expected' => [
                    'key1' => 'value1',
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
            ],
            'initially not having pauseOnBlur provider BUT having options' => [
                'expected' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [
                            'pauseOnBlur' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'configuration for other plugins is preserved' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'pauseOnBlur' => [
                                'autoresume' => false,
                            ],
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testWithPreventScreenshotPlugin(): void
    {
        $expected = [
            'key1' => 'value1',
            'providers' => [
                'plugins' => [
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PREVENT_SCREENSHOT,
                ],
            ],
        ];

        $subject = new DeliveryExecutionConfigurationBuilder(['key1' => 'value1']);

        $this->assertSame($subject, $subject->withPreventScreenshotPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    /**
     * @dataProvider withoutPreventScreenshotPluginDataProvider
     */
    public function testWithoutPreventScreenshotPlugin(
        array $expected,
        array $initialConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withoutPreventScreenshotPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    public function withoutPreventScreenshotPluginDataProvider(): array
    {
        return [
            'initially having preventScreenshot' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [],
                    ],
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PREVENT_SCREENSHOT,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'preventScreenshot' => [
                                'opt' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
            'initially not having preventScreenshot provider nor options' => [
                'expected' => [
                    'key1' => 'value1',
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
            ],
            'initially not having preventScreenshot provider BUT having options' => [
                'expected' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [
                            'preventScreenshot' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'configuration for other plugins is preserved' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PREVENT_SCREENSHOT,
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'preventScreenshot' => [
                                'autoresume' => false,
                            ],
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testWithDisableCommandsPlugin(): void
    {
        $expected = [
            'key1' => 'value1',
            'providers' => [
                'plugins' => [
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_COMMANDS,
                ],
            ],
        ];

        $subject = new DeliveryExecutionConfigurationBuilder(['key1' => 'value1']);

        $this->assertSame($subject, $subject->withDisableCommandsPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    /**
     * @dataProvider withoutDisableCommandsPluginDataProvider
     */
    public function testWithoutDisableCommandsPlugin(
        array $expected,
        array $initialConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withoutDisableCommandsPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    public function withoutDisableCommandsPluginDataProvider(): array
    {
        return [
            'initially having disableCommands' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [],
                    ],
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_COMMANDS,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'disableCommands' => [
                                'opt' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
            'initially not having disableCommands provider nor options' => [
                'expected' => [
                    'key1' => 'value1',
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
            ],
            'initially not having disableCommands provider BUT having options' => [
                'expected' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [
                            'disableCommands' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'configuration for other plugins is preserved' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_COMMANDS,
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'disableCommands' => [
                                'autoresume' => false,
                            ],
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testWithDisableRightClickPlugin(): void
    {
        $expected = [
            'key1' => 'value1',
            'providers' => [
                'plugins' => [
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK,
                ],
            ],
        ];

        $subject = new DeliveryExecutionConfigurationBuilder(['key1' => 'value1']);

        $this->assertSame($subject, $subject->withDisableRightClickPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    /**
     * @dataProvider withoutDisableRightClickPluginDataProvider
     */
    public function testWithoutDisableRightClickPlugin(
        array $expected,
        array $initialConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder($initialConfiguration);

        $this->assertSame($subject, $subject->withoutDisableRightClickPlugin());
        $this->assertEquals($expected, $subject->build());
    }

    public function withoutDisableRightClickPluginDataProvider(): array
    {
        return [
            'initially having DisableRightClick' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [],
                    ],
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'disableRightClick' => [
                                'opt' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
            'initially not having DisableRightClick provider nor options' => [
                'expected' => [
                    'key1' => 'value1',
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                ],
            ],
            'initially not having DisableRightClick provider BUT having options' => [
                'expected' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'options' => [
                        'plugins' => [
                            'disableRightClick' => [
                                'autoresume' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'configuration for other plugins is preserved' => [
                'expected' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
                'initialConfiguration' => [
                    'key1' => 'value1',
                    'providers' => [
                        'plugins' => [
                            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK,
                            self::PLUGIN_PROVIDER_DUMMY,
                        ],
                    ],
                    'options' => [
                        'plugins' => [
                            'disableRightClick' => [
                                'autoresume' => false,
                            ],
                            'Plugin2' => [
                                'option' => 'value',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider overridePluginOptionsDataProvider
     */
    public function testOverridePluginOptions(
        array $expected,
        array $initialConfiguration,
        array $overriddenConfiguration,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder(['options' => ['plugins' => $initialConfiguration]]);

        $this->assertSame($subject, $subject->overridePluginOptions($overriddenConfiguration));
        $this->assertEquals(['options' => ['plugins' => $expected]], $subject->build());
    }

    public function overridePluginOptionsDataProvider(): array
    {
        return [
            [[], [], []],
            [['new_prop' => 'new_value'], [], ['new_prop' => 'new_value']],
            [
                ['new_prop' => 'new_value', 'old_prop' => 'old_value'],
                ['old_prop' => 'old_value'],
                ['new_prop' => 'new_value'],
            ],
            [
                ['new_prop' => 'new_value', 'old_prop' => 'new_value'],
                ['old_prop' => 'old_value'],
                ['new_prop' => 'new_value', 'old_prop' => 'new_value'],
            ],
            [
                [
                    'new_prop' => 'new_value',
                    'old_prop' => ['nested_prop' => ['new_prop' => 'new_value', 'old_prop' => 'old_value']],
                ],
                ['old_prop' => ['nested_prop' => ['old_prop' => 'old_value']]],
                ['new_prop' => 'new_value', 'old_prop' => ['nested_prop' => ['new_prop' => 'new_value']]],
            ],
            [
                ['new_prop' => 'new_value', 'old_prop' => ['nested_prop' => ['old_prop' => 'new_value']]],
                ['old_prop' => ['nested_prop' => ['old_prop' => 'old_value']]],
                ['new_prop' => 'new_value', 'old_prop' => ['nested_prop' => ['old_prop' => 'new_value']]],
            ],
        ];
    }

    /**
     * @dataProvider addRemovePluginDataProvider
     */
    public function testAddRemovePlugin(
        array $expected,
        array $initialPluginProviders,
        array $pluginsToAdd,
        array $pluginsToRemove,
    ): void {
        $subject = new DeliveryExecutionConfigurationBuilder(['providers' => ['plugins' => $initialPluginProviders]]);

        foreach ($pluginsToAdd as $module) {
            $this->assertSame($subject, $subject->addPlugin($module));
        }
        foreach ($pluginsToRemove as $module) {
            $this->assertSame($subject, $subject->removePlugin($module));
        }
        $this->assertEquals(['providers' => ['plugins' => $expected]], $subject->build());
    }

    public function addRemovePluginDataProvider(): array
    {
        return [
            [[], [], [], []],
            [
                [
                    [
                        'id' => 'navigationPlugin1',
                        'category' => 'navigation',
                        'module' => 'test-runner/navigation/plugin1/plugin',
                    ],
                ],
                [],
                ['test-runner/navigation/plugin1/plugin'],
                [],
            ],
            [
                [
                    [
                        'id' => 'navigationPlugin1',
                        'category' => 'navigation',
                        'module' => 'test-runner/navigation/plugin1/plugin',
                    ],
                    [
                        'id' => 'navigationPlugin2',
                        'category' => 'navigation',
                        'module' => 'test-runner/navigation/plugin2/plugin',
                    ],
                ],
                [
                    [
                        'id' => 'navigationPlugin1',
                        'category' => 'navigation',
                        'module' => 'test-runner/navigation/plugin1/plugin',
                    ],
                ],
                ['test-runner/navigation/plugin2/plugin'],
                [],
            ],
            [
                [
                    [
                        'id' => 'navigationPlugin2',
                        'category' => 'navigation',
                        'module' => 'test-runner/navigation/plugin2/plugin',
                    ],
                ],
                [
                    [
                        'id' => 'navigationPlugin1',
                        'category' => 'navigation',
                        'module' => 'test-runner/navigation/plugin1/plugin',
                    ],
                ],
                ['test-runner/navigation/plugin2/plugin'],
                ['test-runner/navigation/plugin1/plugin'],
            ],
        ];
    }
}
