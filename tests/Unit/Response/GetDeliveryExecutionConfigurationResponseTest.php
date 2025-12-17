<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Response;

use App\Domain\Tenant\Model\EmptyTestRunnerTheme;
use App\Response\GetDeliveryExecutionConfigurationResponse;
use App\Tests\Traits\DomainTestingTrait;
use PHPUnit\Framework\TestCase;

class GetDeliveryExecutionConfigurationResponseTest extends TestCase
{
    use DomainTestingTrait;

    public function testGetDeliveryExecution(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $deliveryExecution,
            new EmptyTestRunnerTheme(),
            [],
        );

        $this->assertSame($deliveryExecution, $configurationResponse->getDeliveryExecution());
    }

    public function testGetTestRunnerTheme(): void
    {
        $emptyTestRunnerTheme = new EmptyTestRunnerTheme() ;
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            new EmptyTestRunnerTheme(),
            [],
        );

        $this->assertEquals($emptyTestRunnerTheme, $configurationResponse->getTestRunnerTheme());
    }

    public function testGetTestRunnerConfiguration(): void
    {
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            new EmptyTestRunnerTheme(),
            [
                'foo' => 'bar',
            ],
        );

        $this->assertSame(['foo' => 'bar'], $configurationResponse->getTestRunnerConfiguration());
    }

    public function testShouldOverrideUiLocaleReturnsFalseWhenLocaleIsEmpty(): void
    {
        $response = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            null,
            [],
        );

        $this->assertFalse($response->shouldOverrideUiLocale());
    }

    public function testShouldOverrideUiLocaleReturnsFalseWhenIsMultiLanguageIsFalse(): void
    {
        $response = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            null,
            ['options' => ['localization' => ['locale' => 'en-US', 'isMultiLanguage' => false]]],
        );

        $this->assertFalse($response->shouldOverrideUiLocale());
    }

    public function testShouldOverrideUiLocaleReturnsTrueWhenIsMultiLanguageIsTrue(): void
    {
        $response = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            null,
            ['options' => ['localization' => ['locale' => 'en-US', 'isMultiLanguage' => true]]],
        );

        $this->assertTrue($response->shouldOverrideUiLocale());
    }

    public function testGetTranslatedTestLocaleReturnsNullWhenShouldOverrideUiLocaleIsFalse(): void
    {
        $response = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            null,
            [],
        );

        $this->assertNull($response->getTranslatedTestLocale());
    }

    public function testGetTranslatedTestLocaleReturnsLocaleWhenShouldOverrideUiLocaleIsTrue(): void
    {
        $response = new GetDeliveryExecutionConfigurationResponse(
            $this->createTestDelivery(),
            $this->createTestDeliveryExecution(),
            null,
            ['options' => ['localization' => ['locale' => 'fr-FR', 'isMultiLanguage' => true]]],
        );

        $this->assertSame('fr-FR', $response->getTranslatedTestLocale());
    }
}
