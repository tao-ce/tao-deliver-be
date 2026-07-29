<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Action\DeliveryExecution\GetDeliveryExecutionConfigurationAction;
use App\Builder\DeliveryExecution\DeliveryExecutionConfigurationBuilder;
use App\Domain\Battery\Model\Battery;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Tenant\Model\TestRunnerSettingsRepositoryInterface;
use App\Environment\FeatureFlagAdapterInterface;
use App\Generator\UrlGenerator;
use App\Lti\LtiCustomSettings;
use App\Responder\SerializerResponder;
use App\Service\Battery\BatteryService;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionCreatorInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Lti\LtiLaunchService;
use App\Service\Lti\LtiTokenResolverInterface;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\RealTimeService;
use App\Tests\Stubs\ConfigurationRepositoryStub;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Validator\DeliveryExecution\KioskSettingsValidator;
use Carbon\Carbon;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetDeliveryExecutionConfigurationActionTest extends WebTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use OAuth2SecurityTestingTrait;
    use QtiTestingTrait;

    private const NOW = '2023-02-06T10:45:00+00:00';
    private const PLUGIN_CONFIG_TITLE = [
        'id' => 'titlePlugin',
        'module' => 'taoQtiNuiTest/runner/plugins/content/title/plugin',
        'category' => 'content',
    ];
    private const PLUGIN_CONFIG_MENUPANEL = [
        'id' => 'menuPanelPlugin',
        'module' => 'taoQtiNuiTest/runner/plugins/panel/menu/plugin',
        'category' => 'content',
    ];
    private const PLUGIN_CONFIG_JUMPMENU = [
        'id' => 'jumpMenuPlugin',
        'module' => 'taoQtiNuiTest/runner/plugins/navigation/jumpMenu/plugin',
        'category' => 'content',
    ];
    private const PLUGIN_CONFIG_NAVIGATOR = [
        'id' => 'navigatorPlugin',
        'module' => 'taoQtiNuiTest/runner/plugins/navigation/navigator/plugin',
        'category' => 'content',
    ];
    private const PLUGIN_CONFIG_WARNBEFORELEAVING = [
        'id' => 'warnBeforeLeaving',
        'module' => 'taoQtiNuiTest/runner/plugins/navigation/warnBeforeLeaving/plugin',
        'category' => 'navigation',
    ];
    private const PLUGIN_CONFIG_REFRESH = [
        'id' => 'refresh',
        'module' => 'taoQtiNuiTest/runner/plugins/tools/refresh/plugin',
        'category' => 'tools',
    ];

    private const EXPECTED_ITEM_RUNNER_CONFIG_OVERRIDES_KEYS = [
        'ChoiceInteraction',
        'InlineChoiceInteraction',
        'OrderInteraction',
        'AssociateInteraction',
        'GapMatchInteraction',
        'MatchInteraction',
    ];

    private KernelBrowser $client;
    private string $prefix;
    private GetDeliveryExecutionConfigurationAction $action;
    private BatteryService $batteryServiceMock;
    private BatteryNavigationService $batteryNavigationServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);

        $this->client = static::createClient();
        $this->setUpTestDocumentManager();
        $this->saveDocument($this->createTestDelivery('deliveryId'));

        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Item-Q01/item.json',
                'Item-Q02/item.json',
                'Item-Q03/item.json',
            ],
            'BasicAccessibility',
        );

        $this->batteryServiceMock = $this->createMock(BatteryService::class);
        $this->batteryNavigationServiceMock = $this->createMock(BatteryNavigationService::class);

        $this->action = new GetDeliveryExecutionConfigurationAction(
            $this->createMock(DeliveryExecutionCreatorInterface::class),
            $this->createMock(LtiLaunchService::class),
            $this->createMock(TestRunnerSettingsRepositoryInterface::class),
            $this->createMock(SerializerResponder::class),
            $this->createMock(LtiCustomSettings::class),
            $this->createMock(DeliveryExecutionPropertyService::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(RealTimeService::class),
            $this->createMock(UrlGenerator::class),
            $this->batteryServiceMock,
            $this->batteryNavigationServiceMock,
            $this->createMock(LtiTokenResolverInterface::class),
            $this->createMock(FeatureFlagAdapterInterface::class),
            $this->createMock(KioskSettingsValidator::class),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testTheTenantLinkedToTheConfigurationDoesNotHaveTestRunnerTheme(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '1',
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $this->assertNull($content['data']['themes']);
    }

    public function testItResponsesDeliveryExecutionConfigurationSuccessful(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $tenantIdWithTestRunnerThemes = '2';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantIdWithTestRunnerThemes,
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertEquals($this->getExpectedTestRunnerTheme(), $content['data']['themes']);
    }

    public function testItRejectsCrossSessionRequest(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $tenantIdWithTestRunnerThemes = '2';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantIdWithTestRunnerThemes,
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest(
            $deliveryExecution,
            $ltiParameters,
            "differentUser_{$deliveryExecution->getId()}",
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testItResponsesNotFoundIfDeliveryExecutionDoesNotExist(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution
            ->method('getId')
            ->willReturn('invalid_delivery_execution_id');

        $this->doRequest($deliveryExecution);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testItResponsesDeliveryExecutionConfigurationReviewModeSuccessful(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();

        $ltiParameters['custom'][LtiCustomSettings::PARAM_REVIEW_MODE] = true;

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $stub = new ConfigurationRepositoryStub();
        $this->assertEquals($stub->getProviders(), $content['data']['providers']);
    }

    public function testItSetsNavigationModeAsNone(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_NAVIGATION_MODE] = 'none';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertEquals(
            [
                self::PLUGIN_CONFIG_TITLE,
                self::PLUGIN_CONFIG_MENUPANEL,
                self::PLUGIN_CONFIG_JUMPMENU,
            ],
            $content['data']['providers']['plugins'],
        );
        $this->assertEquals('none', $content['data']['options'][LtiCustomSettings::PARAM_NAVIGATION_MODE]);
    }

    public function testItDisablesReadAloudPluginWhenLtiCustomClaimIsNotProvided(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();

        $tenantId = '6';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'Basic',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertNotContains(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_READ_ALOUD,
            $content['data']['providers']['plugins'],
        );
    }

    public function testItDisablesReadAloudPluginWhenTextToSpeechIsNotEnabledForItems(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_READ_ALOUD_OPTION] = LtiCustomSettings::PARAM_READ_ALOUD_OPTION_ENABLED;

        $tenantId = '6';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'Basic',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertNotContains(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_READ_ALOUD,
            $content['data']['providers']['plugins'],
        );
    }

    public function testItEnablesReadAloudPluginWhenCustomClaimIsProvidedAndAtLeastOneItemHasTextToSpeechEnabled(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_PLUGINS] = json_encode([
            'readAloud' => [
                'readAloudOption' => LtiCustomSettings::PARAM_READ_ALOUD_OPTION_CONTENT_BASED,
            ],
        ]);

        $tenantId = '7';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertContains(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_READ_ALOUD,
            $content['data']['providers']['plugins'],
        );
    }

    public function testItEnablesNonLinearNavigation(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $tenantId = '8';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertTrue($content['data']['options']['nonLinearRestricted']);
    }

    public function testReadAloudDisabledIfOptionProvided(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_PLUGINS] = json_encode([
            'readAloud' => [
                'readAloudOption' => LtiCustomSettings::PARAM_READ_ALOUD_OPTION_DISABLED,
            ],
        ]);

        $tenantId = '7';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertNotContains(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_READ_ALOUD,
            $content['data']['providers']['plugins'],
        );
    }

    public function testItDisabledFullscreenPluginWhenForceFullscreenPluginEnabled(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED] = true;

        $tenantId = '7';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertContains(
            [
                'category' => 'security',
                'id' => 'forceFullscreen',
                'module' => 'taoQtiNuiTest/runner/plugins/security/forceFullscreen/plugin',
            ],
            $content['data']['providers']['plugins'],
        );
        $this->assertNotContains(
            [
                'category' => 'tools',
                'id' => 'fullscreen',
                'module' => 'taoQtiNuiTest/runner/plugins/tools/fullscreen/plugin',
            ],
            $content['data']['providers']['plugins'],
        );
    }

    public function testItResponsesDeliveryExecutionConfigurationWithCustomTitlesSuccessful(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $expectedTitles = [
            [
                'type' => 'test',
                'label' => 'Custom test title value',
            ],
            [
                'type' => 'section',
            ],
            [
                'type' => 'item',
            ],
        ];

        $ltiParameters['custom'][LtiCustomSettings::PARAM_TITLES] = json_encode($expectedTitles);

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertEquals($expectedTitles, $content['data']['options']['titles']);
    }

    /**
     * @dataProvider getDataProvidersForReviewClaims
     */
    public function testItResponsesDeliveryExecutionConfigurationWithReviewClaims(
        array $customClaims,
        ?array $expected,
    ): void {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'] = $customClaims;

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        if (null === $expected) {
            $this->assertArrayNotHasKey('review', $content['data']['options']);
        } else {
            $this->assertEquals($expected, $content['data']['options']['review']);
        }
    }

    public function testItRemovesPluginsInReviewModeAllInOne(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_REVIEW_MODE] = 'true';
        $ltiParameters['custom'][LtiCustomSettings::PARAM_REVIEW_MODE_ALL_IN_ONE] = 'true';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertEquals(
            [
                self::PLUGIN_CONFIG_MENUPANEL,
                self::PLUGIN_CONFIG_JUMPMENU,
            ],
            $content['data']['providers']['plugins'],
        );
    }

    public function testIsEnabledBookletExportPlugin(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'] = [
            LtiCustomSettings::PARAM_REVIEW_MODE => 'true',
            LtiCustomSettings::PARAM_EXPORT_SETTINGS_ENABLED => 'true',
        ];

        $tenantId = '6';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'Basic',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $this->assertEquals(
            [
                'category' => 'content',
                'id' => 'bookletExportPlugin',
                'module' => 'taoQtiNuiTest/runner/plugins/export/bookletExport/plugin',
            ],
            $content['data']['providers']['plugins'][0],
        );
    }

    public function testInlineFeedbackConfigurationForInstructorMode(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['roles'] = ['http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor'];

        $tenantId = '6';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'Basic',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $this->assertEquals(
            ['read', 'write'],
            $content['data']['options']['plugins']['inlineComments']['mode'],
        );
    }


    public function getDataProvidersForReviewClaims(): array
    {
        return [
            'no claims' => [
                [],
                null,
            ],
            'only review' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                ],
                [
                    'showQuestion' => true,
                    'showCorrect' => false,
                    'showScore' => false,
                    'itemLaunch' => false,
                    'showUnShuffled' => false,
                    'allInOne' => false,
                ],
            ],
            'with unShuffled' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_UN_SHUFFLED => true,
                ],
                [
                    'showQuestion' => true,
                    'showCorrect' => false,
                    'showScore' => false,
                    'itemLaunch' => false,
                    'showUnShuffled' => true,
                    'allInOne' => false,
                ],
            ],
            'with correct' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT => true,
                ],
                [
                    'showQuestion' => true,
                    'showCorrect' => true,
                    'showScore' => false,
                    'itemLaunch' => false,
                    'showUnShuffled' => false,
                    'allInOne' => false,
                ],
            ],
            'with score' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_SCORE => true,
                ],
                [
                    'showQuestion' => true,
                    'showCorrect' => true,
                    'showScore' => true,
                    'itemLaunch' => false,
                    'showUnShuffled' => false,
                    'allInOne' => false,
                ],
            ],
            'with question = false' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_SCORE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION => false,
                ],
                [
                    'showQuestion' => false,
                    'showCorrect' => true,
                    'showScore' => true,
                    'itemLaunch' => false,
                    'showUnShuffled' => false,
                    'allInOne' => false,
                ],
            ],
            'with question = true' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_SCORE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION => true,
                ],
                [
                    'showQuestion' => true,
                    'showCorrect' => true,
                    'showScore' => true,
                    'itemLaunch' => false,
                    'showUnShuffled' => false,
                    'allInOne' => false,
                ],
            ],
            'with item id' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_ITEM_LAUNCH => true,
                ],
                [
                    'showQuestion' => true,
                    'showCorrect' => false,
                    'showScore' => false,
                    'itemLaunch' => true,
                    'showUnShuffled' => false,
                    'allInOne' => false,
                ],
            ],
            'with review + allInOne' => [
                [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_REVIEW_MODE_ALL_IN_ONE => true,
                ],
                [
                    'showQuestion' => false,
                    'showCorrect' => false,
                    'showScore' => false,
                    'itemLaunch' => false,
                    'showUnShuffled' => false,
                    'allInOne' => true,
                ],
            ],
        ];
    }

    /**
     * @dataProvider responseHonorsSecurityPluginsFromClaimsDataProvider
     */
    public function testResponseHonorsSecurityPluginsFromClaims(
        array $customClaims,
        ?array $expectedOptions,
        ?array $expectedPlugins,
    ): void {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'] = $customClaims;

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        foreach ($expectedOptions as $expectedOption => $expectedOptionValue) {
            $actualOptionValue = is_array($expectedOptionValue)
                ? array_intersect_key(
                    $content['data']['options'][$expectedOption],
                    array_flip(array_keys($expectedOptionValue)),
                )
                : $content['data']['options'][$expectedOption];

            $this->assertEquals($expectedOptionValue, $actualOptionValue);
        }
        $this->assertEquals($expectedPlugins, $content['data']['providers']['plugins']);
    }

    public function testLocaleIsSetFromDeliveryExecution(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getMainLocale')->willReturn('en-US');

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLocale')->willReturn('fr-FR');
        $deliveryExecution->method('isStateInitial')->willReturn(false);

        $configuration = [];
        $reflection = new ReflectionMethod($this->action, 'setLocaleDetails');

        $result = $reflection->invoke($this->action, $deliveryExecution, $delivery, $configuration);

        $this->assertEquals('fr-FR', $result['options']['localization']['locale']);
        $this->assertEquals('en-US', $result['options']['localization']['mainLocale']);
    }

    public function testLocaleFallsBackToDeliveryMainLocale(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getMainLocale')->willReturn('en-US');

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLocale')->willReturn(null);
        $deliveryExecution->method('isStateInitial')->willReturn(false);

        $configuration = [];
        $reflection = new ReflectionMethod($this->action, 'setLocaleDetails');

        $result = $reflection->invoke($this->action, $deliveryExecution, $delivery, $configuration);

        $this->assertEquals('en-US', $result['options']['localization']['locale']);
        $this->assertEquals('en-US', $result['options']['localization']['mainLocale']);
    }

    public function testLocaleIsNotSetForInitialState(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getMainLocale')->willReturn('en-US');

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLocale')->willReturn(null);
        $deliveryExecution->method('isStateInitial')->willReturn(true);

        $configuration = [];
        $reflection = new ReflectionMethod($this->action, 'setLocaleDetails');

        $result = $reflection->invoke($this->action, $deliveryExecution, $delivery, $configuration);

        $this->assertNull($result['options']['localization']['locale']);
        $this->assertEquals('en-US', $result['options']['localization']['mainLocale']);
    }

    public function testSupportedLocalesAreSetFromDelivery(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getSupportedLocales')->willReturn(['en-US', 'fr-FR']);
        $delivery->method('getMainLocale')->willReturn('en-US');

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLtiLaunchParameters')->willReturn([]);
        $deliveryExecution->method('getLocale')->willReturn('fr-FR');

        $configuration = [];
        $reflection = new ReflectionMethod($this->action, 'setLocaleDetails');

        $result = $reflection->invoke($this->action, $deliveryExecution, $delivery, $configuration);

        $this->assertEquals(['en-US', 'fr-FR'], $result['options']['localization']['supportedLocales']);
        $this->assertEquals('fr-FR', $result['options']['localization']['locale']);
    }

    public function testSupportedLocalesAreSetFromBattery(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getMainLocale')->willReturn('en-US');

        $battery = $this->createMock(Battery::class);

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLtiLaunchParameters')->willReturn(['battery_id' => 'battery123']);
        $deliveryExecution->method('getLocale')->willReturn('fr-FR');

        $this->batteryServiceMock
            ->expects($this->once())
            ->method('findBatteryOrFail')
            ->with('battery123')
            ->willReturn($battery);

        $this->batteryServiceMock
            ->expects($this->once())
            ->method('getCommonLocales')
            ->with($battery)
            ->willReturn(['en-US', 'fr-FR']);

        $configuration = [];
        $reflection = new ReflectionMethod($this->action, 'setLocaleDetails');

        $result = $reflection->invoke($this->action, $deliveryExecution, $delivery, $configuration);

        $this->assertEquals(['en-US', 'fr-FR'], $result['options']['localization']['supportedLocales']);
        $this->assertEquals('fr-FR', $result['options']['localization']['locale']);
    }

    public function testKioskOptionWhenEnabled(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_ENABLED] = true;
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_MINVERSION] = '11.22.33';
        $ltiParameters['custom'][LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED] = true;
        $ltiParameters['custom'][LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED] = true;

        $tenantId = '7';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $this->assertArrayHasKey('kiosk', $content['data']['options']);
        $this->assertEquals([
            'enabled' => true,
            'minVersion' => '11.22.33',
            'pauseOnBreach' => false,
        ], $content['data']['options']['kiosk']);

        $this->assertNotContainsEquals(
            self::PLUGIN_CONFIG_WARNBEFORELEAVING,
            $content['data']['providers']['plugins'],
        );
        $this->assertNotContainsEquals(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
            $content['data']['providers']['plugins'],
        );
        $this->assertContainsEquals(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
            $content['data']['providers']['plugins'],
        );
        $this->assertContainsEquals(
            self::PLUGIN_CONFIG_REFRESH,
            $content['data']['providers']['plugins'],
        );
    }

    public function testKioskOptionWhenNotEnabled(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_ENABLED] = false;
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_MINVERSION] = '11.22.33';
        $ltiParameters['custom'][LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED] = true;

        $tenantId = '7';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $this->assertArrayNotHasKey('kiosk', $content['data']['options']);

        $this->assertContainsEquals(
            self::PLUGIN_CONFIG_WARNBEFORELEAVING,
            $content['data']['providers']['plugins'],
        );
        $this->assertContainsEquals(
            DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
            $content['data']['providers']['plugins'],
        );
        $this->assertNotContainsEquals(
            self::PLUGIN_CONFIG_REFRESH,
            $content['data']['providers']['plugins'],
        );
    }

    public function testKioskOptionWhenProviderIdSet(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_ENABLED] = true;
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_MINVERSION] = '11.22.33';
        $ltiParameters['custom'][LtiCustomSettings::PARAM_KIOSK_PROVIDER_ID] = 'kiosked';

        $tenantId = '7';

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            $tenantId,
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);

        $this->assertArrayHasKey('kiosk', $content['data']['options']);
        $this->assertEquals([
            'enabled' => true,
            'minVersion' => '11.22.33',
            'providerId' => 'kiosked',
            'pauseOnBreach' => false,
        ], $content['data']['options']['kiosk']);
    }

    public function responseHonorsSecurityPluginsFromClaimsDataProvider(): array
    {
        return [
            'no claims' => [
                'customClaims' => [],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with force fullscreen' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => true,
                ],
                'expectedOptions' => [
                    'plugins' => [
                        'forceFullscreen' => [
                            'autoresume' => true,
                        ],
                    ],
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                ],
            ],
            'with force fullscreen and autoresume' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => true,
                    LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME => false,
                ],
                'expectedOptions' => [
                    'plugins' => [
                        'forceFullscreen' => [
                            'autoresume' => false,
                        ],
                    ],
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                ],
            ],
            'with disable commands' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => true,
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_COMMANDS,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK,
                ],
            ],
            'with start time 1h ahead' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 60 * 60 * 1000,
                    'startsAt' => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with start time 1h behind' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->subHour()->format(DATE_ATOM),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => (new Carbon(self::NOW))->subHour()->format(DATE_ATOM),
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with start time and end time' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->addHours(2)->format(DATE_ATOM),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 60 * 60 * 1000,
                    'startsAt' => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    'endsAt' => (new Carbon(self::NOW))->addHours(2)->format(DATE_ATOM),
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with start time and end time 1h behind' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 60 * 60 * 1000,
                    'startsAt' => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with end time but no start time' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with start time in ISO8601 1h behind' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->subHour()->toIso8601String(),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => (new Carbon(self::NOW))->subHour()->format(DATE_ATOM),
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with start time and end time in ISO8601' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->toIso8601String(),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->addHours(2)->toIso8601String(),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 60 * 60 * 1000,
                    'startsAt' => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    'endsAt' => (new Carbon(self::NOW))->addHours(2)->format(DATE_ATOM),
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with start time in ISO8601 and end time in ISO8601 1h behind' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->toIso8601String(),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->toIso8601String(),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 60 * 60 * 1000,
                    'startsAt' => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with end time in ISO8601 but no start time' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->toIso8601String(),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with pause on blur' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => true,
                ],
                'expectedOptions' => [
                    'plugins' => [
                        'pauseOnBlur' => [
                            'autoresume' => true,
                            'threshold' => 0,
                        ],
                    ],
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                ],
            ],
            'with pause on blur and no autoresume' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => true,
                    LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME => false,
                ],
                'expectedOptions' => [
                    'plugins' => [
                        'pauseOnBlur' => [
                            'autoresume' => false,
                            'threshold' => 0,
                        ],
                    ],
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                ],
            ],
            'with prevent screenshot' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => true,
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PREVENT_SCREENSHOT,
                ],
            ],
            'with plugin settings' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_PLUGINS => json_encode([
                        'plugin_id' => [
                            'plugin_option' => 'plugin_option_value',
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                    'testTitle' => 'Basic Test (Linear-Individual)',
                    'plugins' => [
                        'plugin_id' => [
                            'plugin_option' => 'plugin_option_value',
                        ],
                    ],
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_TITLE,
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                ],
            ],
            'with plugin settings in review mode' => [
                'customClaims' => [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                    LtiCustomSettings::PARAM_PLUGINS => json_encode([
                        'readAloud' => [
                            'readAloudOption' => LtiCustomSettings::PARAM_READ_ALOUD_OPTION_ENABLED,
                        ],
                        'plugin_id' => [
                            'plugin_option' => 'plugin_option_value',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => true,
                    LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => true,
                    LtiCustomSettings::PARAM_REMOVE_PLUGINS => self::PLUGIN_CONFIG_TITLE['module'],
                    // the ones down below should be ignored in the review more
                    LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => true,
                    LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => true,
                    LtiCustomSettings::PARAM_ADD_PLUGINS => 'taoQtiNuiTest/runner/plugins/panel/a11y/plugin',
                ],
                'expectedOptions' => [
                    'waitTimeRemaining' => 0,
                    'startsAt' => null,
                    'endsAt' => null,
                    'testTitle' => 'Basic Test (Linear-Individual)',
                ],
                'expectedPlugins' => [
                    self::PLUGIN_CONFIG_MENUPANEL,
                    self::PLUGIN_CONFIG_JUMPMENU,
                    self::PLUGIN_CONFIG_NAVIGATOR,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_FORCE_FULLSCREEN,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PAUSE_ON_BLUR,
                    [
                        'id' => 'panelA11y',
                        'category' => 'panel',
                        'module' => 'taoQtiNuiTest/runner/plugins/panel/a11y/plugin',
                    ],
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_READ_ALOUD,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_PREVENT_SCREENSHOT,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_COMMANDS,
                    DeliveryExecutionConfigurationBuilder::PLUGIN_CONFIG_DISABLE_RIGHT_CLICK,
                ],
            ],
        ];
    }

    public function testItMergesItemRunnerConfigFromClaims(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $customClaim = [
            'ExtendedTextInteraction' => [
                'propertyOverride' => [
                    'uploadMaxSize' => 77,
                ],
                'spellCheckConfig' => [
                    'enabled' => true,
                ],
            ],
        ];
        $expectedConfig = [
            'propertyOverride' => [
                'dataAttrs' => [
                    'data-image-upload' => 'true',
                    'data-word-count' => 'true',
                ],
                'uploadMaxSize' => 77,
                'uploadTimeout' => 60000,
            ],
            'spellCheckConfig' => [
                'enabled' => true,
            ],
        ];

        $ltiParameters['custom'][LtiCustomSettings::PARAM_ITEM_RUNNER_CONFIG_ELEMENTS] = json_encode($customClaim);

        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '5', // tenant with test runner configuration
            'Basic',
            $ltiParameters,
        );
        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertEquals($expectedConfig, $content['data']['options']['itemRunnerConfig']['elements']['ExtendedTextInteraction']);
    }

    public function testItReturnsPredefinedCustomIds(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            'withMultipleCustomUiIds',
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertSame(['id-1', 'id-2'], $content['data']['options']['customUiId']);
    }

    public function testItSetsCustomIds(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_CUSTOM_UI_IDS] = 'lti_id-1,lti_id-2';
        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            '1',
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertSame(['lti_id-1', 'lti_id-2'], $content['data']['options']['customUiId']);
    }

    public function testItMergesCustomIds(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_CUSTOM_UI_IDS] = 'lti_id-1,lti_id-2,id-1';
        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            'withMultipleCustomUiIds',
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertSame(['id-1', 'id-2', 'lti_id-1', 'lti_id-2'], $content['data']['options']['customUiId']);
    }

    public function testItMergesCustomIdsWithSingleConfigurationValue(): void
    {
        $ltiParameters = $this->getDummyLtiParameters();
        $ltiParameters['custom'][LtiCustomSettings::PARAM_CUSTOM_UI_IDS] = 'lti_id-1,lti_id-2';
        $deliveryExecution = $this->createDeliveryExecutionWithTestSession(
            'withSingleCustomUiId',
            'BasicAccessibility',
            $ltiParameters,
        );

        $this->doRequest($deliveryExecution, $ltiParameters);

        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertsForSuccessfulCall($deliveryExecution, $content);
        $this->assertSame(['id-1', 'lti_id-1', 'lti_id-2'], $content['data']['options']['customUiId']);
    }

    private function doRequest(
        DeliveryExecution $deliveryExecution,
        array $ltiParameters = [],
        string $accessTokenId = '',
    ): void {
        $this->prefix = filter_var(
            $ltiParameters['custom'][LtiCustomSettings::PARAM_REVIEW_MODE] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        )
            ? DeliveryExecution::REVIEW_MODE_PREFIX . DeliveryExecution::DOCUMENT_KEY_DELIMITER
            : '';

        $id = "$this->prefix{$deliveryExecution->getId()}";

        $accessTokenId = $accessTokenId ?: $id;

        $ltiClaims = [];
        if (!empty($ltiParameters['custom'])) {
            $ltiClaims[LtiMessagePayloadInterface::CLAIM_LTI_CUSTOM] = $ltiParameters['custom'];
        }
        if (!empty($ltiParameters['roles'])) {
            $ltiClaims[LtiMessagePayloadInterface::CLAIM_LTI_ROLES] = $ltiParameters['roles'];
        }

        $this->client->request(
            Request::METHOD_GET,
            sprintf('/api/v1/delivery-executions/%s/configuration', urlencode($id)),
            server: ['HTTP_AUTHORIZATION' => "Bearer {$this->createOAuth2AccessToken($accessTokenId, $ltiClaims)}"],
        );
    }

    private function getDummyLtiParameters(): array
    {
        return [
            'user_id' => 'user_id',
            'user_name' => 'Full Name',
            'given_name' => 'First Name',
            'family_name' => 'Last Name',
        ];
    }

    private function getExpectedTestRunnerTheme(): array
    {
        return [
            'platform' => ['platform'],
            'testRunner' => ['testRunner'],
            'itemRunner' => ['itemRunner'],
            'default' => 'default',
        ];
    }

    private function assertsForSuccessfulCall(DeliveryExecution $deliveryExecution, array $responseData): void
    {
        $prefix = $this->prefix;
        $this->prefix = '';
        $ltiParameters = $deliveryExecution->getLtiLaunchParameters();

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];

        $this->assertEquals("$prefix{$deliveryExecution->getId()}", $data['deliveryExecutionId']);
        $this->assertEquals(static::getContainer()->getParameter('kernel.default_locale'), $data['locale']);
        $this->assertArrayHasKey('testTaker', $data);
        $this->assertEquals($ltiParameters['user_id'], $data['testTaker']['id']);
        if (!$prefix) {
            $this->assertEquals($ltiParameters['user_name'], $data['testTaker']['name']);
            $this->assertEquals($ltiParameters['given_name'], $data['testTaker']['firstName']);
            $this->assertEquals($ltiParameters['family_name'], $data['testTaker']['lastName']);
        }
    }

    private function createDeliveryExecutionWithTestSession(
        string $tenantId,
        string $packageName,
        array $ltiParameters,
    ): DeliveryExecution {
        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Item-Q01/item.json',
                'Item-Q02/item.json',
                'Item-Q03/item.json',
            ],
            $packageName,
        );

        $delivery = $this->createTestDelivery(
            $packageName,
            $tenantId,
            $packageName . '/compact-test.xml',
        );

        $deliveryExecution = $this->createTestDeliveryExecution(
            'di_resu#' . $packageName . '#resultId#' . $tenantId,
            $delivery->getId(),
            $tenantId,
            $ltiParameters,
            'CgEATQEAAAENAFRlc3RQYXJ0LVRQMDEAABAAAwAAAAAAAQAAAAABAAABAAABAQAAAQQAUFQwUwcAdW5rbm93bgACAAAAAAAAAAEAAAAAAAAAAAEAAAAAAAEAAAAAAQAAAAEAAAAAAQEAAAAAAAEAAAAEAFBUMFMNAG5vdF9hdHRlbXB0ZWQAAgAAAQAAAAABAAAAAAAAAAABAAEAAAABAAAAAAIAAAABAAAAAAECAAAAAAABAAAABABQVDBTDQBub3RfYXR0ZW1wdGVkAAIAAAIAAAAAAQAAAAAAAAAAAQACAAAAAQAAAAMACABUZXN0LVQwMQABBABQVDBTDQBUZXN0UGFydC1UUDAxAAEEAFBUMFMLAFNlY3Rpb24tUzAxAAEEAFBUMFM=',
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        $this->saveDocument($delivery);
        $this->saveDocument($deliveryExecution);

        return $deliveryExecution;
    }
}
