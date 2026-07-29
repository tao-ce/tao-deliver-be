<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Builder\DeliveryExecution;

use App\Lti\Exception\LtiCustomSettingsException;

class DeliveryExecutionConfigurationBuilder
{
    public const PLUGIN_CONFIG_FORCE_FULLSCREEN = [
        'id' => 'forceFullscreen',
        'category' => 'security',
        'module' => 'taoQtiNuiTest/runner/plugins/security/forceFullscreen/plugin',
    ];

    public const PLUGIN_CONFIG_PAUSE_ON_BLUR = [
        'id' => 'pauseOnBlur',
        'category' => 'security',
        'module' => 'taoQtiNuiTest/runner/plugins/security/pauseOnBlur/plugin',
    ];

    public const PLUGIN_CONFIG_PREVENT_SCREENSHOT = [
        'id' => 'preventScreenshot',
        'category' => 'security',
        'module' => 'taoQtiNuiTest/runner/plugins/security/preventScreenshot/plugin',
    ];

    public const PLUGIN_CONFIG_DISABLE_COMMANDS = [
        'id' => 'disableCommands',
        'category' => 'security',
        'module' => 'taoQtiNuiTest/runner/plugins/security/disableCommands/plugin',
    ];

    public const PLUGIN_CONFIG_DISABLE_RIGHT_CLICK = [
        'id' => 'disableRightClick',
        'category' => 'security',
        'module' => 'taoQtiNuiTest/runner/plugins/security/disableRightClick/plugin',
    ];

    public const PLUGIN_CONFIG_FULLSCREEN = [
        'id' => 'fullscreen',
        'category' => 'tools',
        'module' => 'taoQtiNuiTest/runner/plugins/tools/fullscreen/plugin',
    ];

    public const PLUGIN_CONFIG_REFRESH = [
        'id' => 'refresh',
        'category' => 'tools',
        'module' => 'taoQtiNuiTest/runner/plugins/tools/refresh/plugin',
    ];

    public const PLUGIN_CONFIG_READ_ALOUD = [
        'id' => 'readAloud',
        'module' => 'taoQtiNuiTest/runner/plugins/tools/readAloud/plugin',
        'category' => 'tools',
    ];

    public const PLUGIN_CONFIG_MENU_PANEL = [
        'id' => 'menuPanelPlugin',
        'module' => 'taoQtiNuiTest/runner/plugins/panel/menu/plugin',
        'category' => 'content',
    ];

    public const PLUGIN_CONFIG_KIOSK = [
        'id' => 'kiosk',
        'category' => 'security',
        'module' => 'taoQtiNuiTest/runner/plugins/security/kiosk/plugin',
    ];

    private const PLUGIN_OPTION_AUTORESUME = 'autoresume';
    private const PLUGIN_MODULE_PATTERN = '~(?<category>[^/]+)/(?<plugin>[^/]+)/plugin$~';

    private array $configuration = [];

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function build(): array
    {
        return $this->configuration;
    }

    public function withSettingsPlugin(): self
    {
        return $this->addPlugin('taoQtiNuiTest/runner/plugins/settings/plugin');
    }

    public function withForceFullScreenPlugin(bool $autoresume): self
    {
        return $this
            ->addPluginProvider(self::PLUGIN_CONFIG_FORCE_FULLSCREEN)
            ->setPluginOption(
                self::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                self::PLUGIN_OPTION_AUTORESUME,
                $autoresume,
            );
    }

    public function withoutForceFullScreenPlugin(): self
    {
        return $this
            ->removePluginProvider(self::PLUGIN_CONFIG_FORCE_FULLSCREEN)
            ->removePluginOptions(self::PLUGIN_CONFIG_FORCE_FULLSCREEN);
    }

    public function withoutFullScreenPlugin(): self
    {
        return $this->removePluginProvider(self::PLUGIN_CONFIG_FULLSCREEN);
    }

    public function withRefreshPlugin(): self
    {
        return $this->addPluginProvider(self::PLUGIN_CONFIG_REFRESH);
    }

    public function withPauseOnBlurPlugin(bool $autoresume): self
    {
        return $this
            ->addPluginProvider(self::PLUGIN_CONFIG_PAUSE_ON_BLUR)
            ->setPluginOption(
                self::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                self::PLUGIN_OPTION_AUTORESUME,
                $autoresume,
            );
    }

    public function withoutPauseOnBlurPlugin(): self
    {
        return $this
            ->removePluginProvider(self::PLUGIN_CONFIG_PAUSE_ON_BLUR)
            ->removePluginOptions(self::PLUGIN_CONFIG_PAUSE_ON_BLUR);
    }

    public function withPreventScreenshotPlugin(): self
    {
        return $this->addPluginProvider(self::PLUGIN_CONFIG_PREVENT_SCREENSHOT);
    }

    public function withoutReadAloudPlugin(): self
    {
        return $this->removePluginProvider(self::PLUGIN_CONFIG_READ_ALOUD);
    }

    public function withReadAloudPlugin(): self
    {
        return $this->addPluginProvider(self::PLUGIN_CONFIG_READ_ALOUD);
    }

    public function withoutPreventScreenshotPlugin(): self
    {
        return $this
            ->removePluginProvider(self::PLUGIN_CONFIG_PREVENT_SCREENSHOT)
            ->removePluginOptions(self::PLUGIN_CONFIG_PREVENT_SCREENSHOT);
    }

    public function withDisableCommandsPlugin(): self
    {
        return $this->addPluginProvider(self::PLUGIN_CONFIG_DISABLE_COMMANDS);
    }

    public function withoutDisableCommandsPlugin(): self
    {
        return $this
            ->removePluginProvider(self::PLUGIN_CONFIG_DISABLE_COMMANDS)
            ->removePluginOptions(self::PLUGIN_CONFIG_DISABLE_COMMANDS);
    }

    public function withDisableRightClickPlugin(): self
    {
        return $this->addPluginProvider(self::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK);
    }

    public function withoutDisableRightClickPlugin(): self
    {
        return $this
            ->removePluginProvider(self::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK)
            ->removePluginOptions(self::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK);
    }

    public function withKioskPlugin(): self
    {
        return $this
            ->addPluginProvider(self::PLUGIN_CONFIG_KIOSK);
    }

    public function overridePluginOptions(array $pluginSettings): self
    {
        $this->configuration['options']['plugins'] = $this->mergePluginConfiguration(
            $this->configuration['options']['plugins'] ?? [],
            $pluginSettings,
        );

        return $this;
    }

    public function addPlugin(string $module): self
    {
        return $this->addPluginProvider(
            $this->createPluginProvider($module),
        );
    }

    public function removePlugin(string $module): self
    {
        return $this->removePluginProvider(
            $this->createPluginProvider($module),
        );
    }

    private function createPluginProvider(string $module): array
    {
        if (!preg_match(self::PLUGIN_MODULE_PATTERN, $module, $matches)) {
            throw new LtiCustomSettingsException("Cannot apply `$module` as a plugin");
        }

        return [
            'id' => $matches['category'] . ucfirst($matches['plugin']),
            'category' => $matches['category'],
            'module' => $module,
        ];
    }

    private function addPluginProvider(array $plugin): self
    {
        if ($this->isPluginProviderEnabled($plugin)) {
            return $this;
        }

        $this->configuration['providers']['plugins'][] = [
            'category' => $plugin['category'],
            'id' => $plugin['id'],
            'module' => $plugin['module'],
        ];

        return $this;
    }

    private function addProvider(array $provider): self
    {
        $this->configuration['providers'][$provider['key']] = $provider;

        return $this;
    }

    private function removePluginProvider(array $plugin): self
    {
        if (!empty($this->configuration['providers']['plugins'])) {
            $module = $plugin['module'];

            $this->configuration['providers']['plugins'] = array_values(
                array_filter(
                    $this->configuration['providers']['plugins'],
                    static function ($plugin) use ($module) {
                        return $plugin['module'] !== $module;
                    },
                ),
            );
        }

        return $this;
    }

    private function isPluginProviderEnabled(array $plugin): bool
    {
        if (!empty($this->configuration['providers']['plugins'])) {
            return in_array($plugin, $this->configuration['providers']['plugins']);
        }

        return false;
    }

    private function setPluginOption(array $plugin, string $option, mixed $value): self
    {
        $pluginId = $plugin['id'];

        if (!isset($this->configuration['options']['plugins'][$pluginId])) {
            $this->configuration['options']['plugins'][$pluginId] = [];
        }

        $this->configuration['options']['plugins'][$pluginId][$option] = $value;

        return $this;
    }

    private function removePluginOptions(array $plugin): self
    {
        unset($this->configuration['options']['plugins'][$plugin['id']]);

        return $this;
    }

    private function mergePluginConfiguration(array $configuration1, array $configuration2): array
    {
        foreach ($configuration2 as $key => $value) {
            $configuration1[$key] =
                is_array($configuration1[$key] ?? null)
                && is_array($value)
                && !array_is_list($value)
                    ? $this->mergePluginConfiguration($configuration1[$key], $value)
                    : $value;
        }

        return $configuration1;
    }
}
