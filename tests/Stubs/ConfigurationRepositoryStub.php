<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Stubs;

use JsonException;
use OAT\Library\EnvironmentManagementClient\Http\AuthorizationDetailsMarkerInterface;
use OAT\Library\EnvironmentManagementClient\Model\Configuration;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use RuntimeException;

class ConfigurationRepositoryStub implements ConfigurationRepositoryInterface
{
    public function __construct()
    {
    }

    /**
     * @throws JsonException
     */
    public function find(string $tenantId, string $configId): Configuration
    {
        if ($configId === 'testRunnerConfiguration') {
            $providers = $this->getProviders();
            if ($tenantId === '7') {
                $providers['plugins'] = [
                    [
                        'id' => 'readAloud',
                        'module' => 'taoQtiNuiTest/runner/plugins/tools/readAloud/plugin',
                        'category' => 'tools',
                    ],
                ];
            }
            return new Configuration(
                'testRunnerConfiguration',
                [
                    'origin' => 'origin',
                    'providers' => $providers,
                    'options' => $this->getOptions(),
                    "exportProviders" => [
                        "proxy" => [
                            "module" => "taoQtiNuiTest/runner/proxy/actionProxy",
                            "id" => "actions-proxy",
                            "category" => "proxy",
                        ],
                        "itemRunner" => [
                            "module" => "taoQtiNuiItem/runner/qti",
                            "id" => "qtinui",
                            "category" => "runner",
                        ],
                        "plugins" => [
                            [
                                "module" => "taoQtiNuiTest/runner/plugins/export/bookletExport/plugin",
                                "id" => "bookletExportPlugin",
                                "category" => "content",
                            ],
                        ],
                        "runner" => [
                            "module" => "taoQtiNuiTest/runner/qtiExport",
                            "id" => "qtinuiExport",
                            "category" => "runner",
                        ],
                    ],
                ],
            );
        }

        if ($configId == 'deliver.provisioned_events') {
            return new Configuration(
                'deliver.provisioned_events',
                [
                    "assessmentLog" => [
                        "proctorActions" => ["*"],
                        "systemActions" => ["*"],
                        "testTakerActions" => ["*"],
                    ],
                ],
            );
        }

        if ($tenantId === '1' && $configId === 'testRunnerTheme') {
            return new Configuration('testRunnerTheme', []);
        }

        if ($configId === 'testRunnerTheme') {
            return new Configuration(
                'testRunnerTheme',
                [
                    'platform' => ['platform'],
                    'testRunner' => ['testRunner'],
                    'itemRunner' => ['itemRunner'],
                    'default' => 'default',
                ],
            );
        }

        if ($configId === 'AUTH_MODE') {
            return new Configuration(
                'AUTH_MODE',
                AuthorizationDetailsMarkerInterface::MODE_SESSION_STORAGE,
            );
        }

        throw new RuntimeException("incorrect config id $configId");
    }

    public function getProviders(): array
    {
        return [
            'runner' => [
                'id' => 'qtinui',
                'module' => 'taoQtiNuiTest/runner/qtiReview',
                'category' => 'runner',
            ],
            'itemRunner' => [
                'id' => 'qtinui',
                'module' => 'taoQtiNuiItem/runner/qti',
                'category' => 'runner',
            ],
            'proxy' => [
                'id' => 'actions-proxy',
                'module' => 'taoQtiNuiTest/runner/proxy/reviewProxy',
                'category' => 'proxy',
            ],
            'plugins' => [
                [
                    'id' => 'titlePlugin',
                    'module' => 'taoQtiNuiTest/runner/plugins/content/title/plugin',
                    'category' => 'content',
                ],
                [
                    'id' => 'menuPanelPlugin',
                    'module' => 'taoQtiNuiTest/runner/plugins/panel/menu/plugin',
                    'category' => 'content',
                ],
                [
                    'id' => 'jumpMenuPlugin',
                    'module' => 'taoQtiNuiTest/runner/plugins/navigation/jumpMenu/plugin',
                    'category' => 'content',
                ],
                [
                    'id' => 'navigatorPlugin',
                    'module' => 'taoQtiNuiTest/runner/plugins/navigation/navigator/plugin',
                    'category' => 'content',
                ],
            ],
        ];
    }

    public function getOptions(): array
    {
        return [
            'itemRunnerConfig' => [
                'elements' => [
                    'ExtendedTextInteraction' => [
                        'propertyOverride' => [
                            'dataAttrs' => [
                                'data-image-upload' => 'true',
                                'data-word-count' => 'true',
                            ],
                            'uploadMaxSize' => 15000000,
                            'uploadTimeout' => 60000,
                        ],
                    ],
                    'UploadInteraction' => [
                        'propertyOverride' => [
                            'maxSize' => 20000000,
                        ],
                    ],
                    'ChoiceInteraction' => [
                        'propertyOverride' => [
                            'shuffle' => true,
                            'dataAttrs' => [
                                'data-math-entry-keyboards' => 'custom1 roman',
                                'data-image-upload' => 'true',
                                'data-word-count' => 'true',
                                'data-special-characters' => 'latinAndMaths',
                            ],
                        ],
                    ],
                ],
            ],
            'liteMode' => false,
            'locale' => 'en-US',
            'plugins' => [
                'localItemState' => [
                    'saveState' => [
                        'enabled' => true,
                        'liveSaveIndicator' => [
                            'enabled' => true,
                        ],
                        'maxWait' => 20000,
                        'minWait' => 5000,
                    ],
                ],
                'pauseOnBlur' => [
                    'threshold' => 0,
                ],
                'preloadNextItemAssets' => [
                    'preloadStrategy' => [
                        'audios' => true,
                        'audiosThreshold' => 3000000,
                        'images' => true,
                        'stylesheets' => true,
                        'videos' => true,
                        'videosThreshold' => 3000000,
                    ],
                ],
            ],
            'proxy' => [
                'preloadItemStoreCapacity' => 30,
                'preloadSectionItemsAmount' => 5,
                'preloadStrategy' => 'sectionItems',
            ],
        ];
    }
}
