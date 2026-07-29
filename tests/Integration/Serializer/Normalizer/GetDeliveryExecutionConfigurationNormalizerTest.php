<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Serializer\Normalizer;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Tenant\Model\EmptyTestRunnerTheme;
use App\Generator\UrlGenerator;
use App\Lti\LtiCustomSettings;
use App\Response\GetDeliveryExecutionConfigurationResponse;
use App\Serializer\Normalizer\GetDeliveryExecutionConfigurationNormalizer;
use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use OAT\Library\TenantManagement\Model\TestRunnerTheme;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class GetDeliveryExecutionConfigurationNormalizerTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;

    /** @var Delivery */
    private $delivery;

    /** @var GetDeliveryExecutionConfigurationNormalizer */
    private $subject;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->copyCompiledTestToStorage();
        $this->delivery = $this->createTestDelivery('deliveryId');
        $this->subject = static::getContainer()->get(GetDeliveryExecutionConfigurationNormalizer::class);
    }

    public function testNormalizationSupport(): void
    {
        $getDeliveryExecutionConfigurationResponse = $this->createMock(GetDeliveryExecutionConfigurationResponse::class);

        $this->assertTrue($this->subject->supportsNormalization($getDeliveryExecutionConfigurationResponse));
        $this->assertFalse($this->subject->supportsNormalization('invalid'));
    }

    /**
     * @dataProvider dataProviderTestNormalize
     */
    public function testNormalize(?string $expectedLocale, Delivery $delivery, DeliveryExecution $deliveryExecution, array $tenantConfiguration = []): void
    {
        $testRunnerConfiguration = array_merge(
            [
                'foo' => 'bar',
            ],
            $tenantConfiguration,
        );

        $testRunnerTheme = new TestRunnerTheme(
            ['platform'],
            ['testRunner'],
            ['itemRunner'],
            'default',
        );

        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $delivery,
            $deliveryExecution,
            $testRunnerTheme,
            $testRunnerConfiguration,
        );

        $normalization = $this->subject->normalize($configurationResponse);
        $this->assertEquals([
            'data' => [
                'deliveryExecutionId' => $deliveryExecution->getId(),
                'locale' => $expectedLocale,
                'testTaker' => [
                    'id' => 'user_id',
                    'name' => 'user_name',
                    'firstName' => null,
                    'lastName' => null,
                ],
                'options' => [
                    'exitUrl' => null,
                    'endAssessmentUrl' => null,
                ],
                'themes' => [
                    'platform' => ['platform'],
                    'testRunner' => ['testRunner'],
                    'itemRunner' => ['itemRunner'],
                    'default' => 'default',
                ],
                'foo' => 'bar',
                'hasItemState' => false,
                'deliveryId' => 'deliveryId',
            ],
        ], $normalization);
    }

    public function dataProviderTestNormalize(): array
    {
        self::bootKernel();

        return [
            'Delivery Execution with default locale' => [
                static::getContainer()->getParameter('kernel.default_locale'),
                $this->createTestDelivery('deliveryId'),
                $this->createTestDeliveryExecution(
                    id: 'di_resu#deliveryId#resultId#tenantId',
                    ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name'],
                    testSession: '',
                ),
            ],
            'Delivery Execution with default + tenant locale' => [
                'tenantLocale',
                $this->createTestDelivery('deliveryId'),
                $this->createTestDeliveryExecution(
                    id: 'di_resu#deliveryId#resultId#tenantId',
                    ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name'],
                    testSession: '',
                ),
                ['locale' => 'tenantLocale'],
            ],
            'Delivery Execution with default + tenant + delivery locale' => [
                'defaultLocale',
                $this->createTestDelivery(
                    'deliveryId',
                    '1',
                    'compactTestFilePath',
                    ['locale' => 'defaultLocale'],
                ),
                $this->createTestDeliveryExecution(
                    id: 'di_resu#deliveryId#resultId#tenantId',
                    ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name'],
                    testSession: '',
                ),
                ['locale' => 'tenantLocale'],
            ],
            'Delivery Execution with default + tenant + user identity locale' => [
                'userIdentityLocale',
                $this->createTestDelivery(
                    'deliveryId',
                    '1',
                    'compactTestFilePath',
                    ['locale' => 'defaultLocale'],
                ),
                $this->createTestDeliveryExecution(
                    id: 'di_resu#deliveryId#resultId#tenantId',
                    ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name', 'user_locale' => 'userIdentityLocale'],
                    testSession: '',
                ),
                ['locale' => 'tenantLocale'],
            ],
            'Delivery Execution with default + tenant + user identity + launch presentation locale' => [
                'launchPresentationLocale',
                $this->createTestDelivery(
                    'deliveryId',
                    '1',
                    'compactTestFilePath',
                    ['locale' => 'defaultLocale'],
                ),
                $this->createTestDeliveryExecution(
                    id: 'di_resu#deliveryId#resultId#tenantId',
                    ltiLaunchParameters: [
                        'user_id' => 'user_id',
                        'user_name' => 'user_name',
                        'user_locale' => 'userIdentityLocale',
                        'launch_presentation_locale' => 'launchPresentationLocale',
                    ],
                    testSession: '',
                ),
                ['locale' => 'tenantLocale'],
            ],
        ];
    }

    public function testNormalizeIfConfigurationAndThemeAreNotDefined(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            id: 'di_resu#deliveryId#resultId#tenantId',
            ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name'],
            testSession: '',
        );

        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            null,
            null,
        );

        $normalization = $this->subject->normalize($configurationResponse);

        $this->assertEquals([
            'data' => [
                'deliveryExecutionId' => $deliveryExecution->getId(),
                'locale' => static::getContainer()->getParameter('kernel.default_locale'),
                'testTaker' => [
                    'id' => 'user_id',
                    'name' => 'user_name',
                    'firstName' => null,
                    'lastName' => null,
                ],
                'options' => [
                    'exitUrl' => null,
                    'endAssessmentUrl' => null,
                ],
                'themes' => null,
                'hasItemState' => false,
                'deliveryId' => 'deliveryId',
            ],
        ], $normalization);
    }

    public function testItReturnsExitUrlFromLtiParameters(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: [
                'user_id' => 'user_id',
                'user_name' => 'user_name',
                'launch_presentation_return_url' => 'https://taotesting.com',
            ],
            testSession: '',
        );
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            new EmptyTestRunnerTheme(),
        );
        $normalization = $this->subject->normalize($configurationResponse);

        $this->assertEquals('https://taotesting.com', $normalization['data']['options']['exitUrl']);
    }

    public function testItReturnsNullExitUrlIfLtiParameterNotProvided(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name'],
            testSession: '',
        );
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            new EmptyTestRunnerTheme(),
        );
        $normalization = $this->subject->normalize($configurationResponse);

        $this->assertNull($normalization['data']['options']['exitUrl']);
    }

    public function testItSetsNoUserIdForAnonymousDeliveryExecutions(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            id: 'a7c014c5d507-suomynona#deliveryId#resultId#tenantId',
            ltiLaunchParameters: ['user_id' => 'a7c014c5d507-suomynona', 'user_name' => null],
            testSession: '',
        );
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            new EmptyTestRunnerTheme(),
        );
        $normalization = $this->subject->normalize($configurationResponse);

        foreach ($normalization['data']['testTaker'] as $key => $value) {
            $this->assertNull($value, "testTaker['$key'] is expected to be `null`");
        }
    }

    public function testItShowsRealNameWhenNoLtiTokenIsPresent(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            id: 'di_resu#deliveryId#resultId#tenantId',
            ltiLaunchParameters: [
                'user_id' => 'user_id',
                'user_name' => 'Real User Name',
                'given_name' => 'Real',
                'family_name' => 'Name',
            ],
            testSession: '',
        );
        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            new EmptyTestRunnerTheme(),
        );

        $normalization = $this->subject->normalize($configurationResponse);

        $this->assertEquals('user_id', $normalization['data']['testTaker']['id']);
        $this->assertEquals('Real User Name', $normalization['data']['testTaker']['name']);
        $this->assertEquals('Real', $normalization['data']['testTaker']['firstName']);
        $this->assertEquals('Name', $normalization['data']['testTaker']['lastName']);
    }

    public function testItShowsAnonymizedNameWhenIsAnonymousScoringClaimIsTrueAndIsReview(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            id: 'review#di_resu#deliveryId#resultId#tenantId',
            ltiLaunchParameters: [
                'user_id' => 'user_id',
                'user_name' => 'Real User Name',
                'given_name' => 'Real',
                'family_name' => 'Name',
            ],
            testSession: '',
        );

        $ltiCustomSettingsMock = $this->createMock(LtiCustomSettings::class);
        $ltiCustomSettingsMock->method('isAnonymousScoring')->willReturn(true);
        $ltiCustomSettingsMock->method('getTestTakerName')->willReturn('anon_abc123');

        $normalizer = new GetDeliveryExecutionConfigurationNormalizer(
            static::getContainer()->get(UserLocaleProviderInterface::class),
            static::getContainer()->get(UrlGenerator::class),
            $ltiCustomSettingsMock,
        );

        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            new EmptyTestRunnerTheme(),
        );

        $normalization = $normalizer->normalize($configurationResponse);

        $this->assertEquals('user_id', $normalization['data']['testTaker']['id']);
        $this->assertEquals('anon_abc123', $normalization['data']['testTaker']['name']);
        $this->assertNull($normalization['data']['testTaker']['firstName']);
        $this->assertNull($normalization['data']['testTaker']['lastName']);
    }

    public function testItMergesDeepArrayProperly(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            id: 'di_resu#deliveryId#resultId#tenantId',
            ltiLaunchParameters: ['user_id' => 'user_id', 'user_name' => 'user_name'],
            testSession: '',
        );

        $configurationResponse = new GetDeliveryExecutionConfigurationResponse(
            $this->delivery,
            $deliveryExecution,
            null,
            [
                'foo' => 'bar',
                'options' => [
                    'foo' => [
                        'bar' => [
                            'a' => 1,
                            'b' => 2,
                        ],
                    ],
                ],
            ],
        );

        $normalization = $this->subject->normalize($configurationResponse);

        $this->assertEquals([
            'data' => [
                'foo' => 'bar',
                'deliveryExecutionId' => $deliveryExecution->getId(),
                'locale' => static::getContainer()->getParameter('kernel.default_locale'),
                'testTaker' => [
                    'id' => 'user_id',
                    'name' => 'user_name',
                    'firstName' => null,
                    'lastName' => null,
                ],
                'options' => [
                    'exitUrl' => null,
                    'endAssessmentUrl' => null,
                    'foo' => [
                        'bar' => [
                            'a' => 1,
                            'b' => 2,
                        ],
                    ],
                ],
                'themes' => null,
                'hasItemState' => false,
                'deliveryId' => 'deliveryId',
            ],
        ], $normalization);
    }
}
