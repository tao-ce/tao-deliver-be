<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti;

use App\Lti\Exception\LtiCustomSettingsException;
use App\Lti\LtiCustomSettings;
use App\Service\Lti\LtiTokenResolver;
use Carbon\Carbon;
use DateTimeInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LtiCustomSettingsTest extends KernelTestCase
{
    private const NOW = '2021-01-01T00:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        Carbon::setTestNow(self::NOW);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsDryRunEnabledReturnsValueAccordingToLtiLaunchParameters($expected, $actual): void
    {
        $this->assertFalse(
            $this->createSubject()->isDryRunEnabled(
                $this->createLtiLaunchParameters([]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isDryRunEnabled(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_DRY_RUN => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isDryRunEnabled(
                $this->createLtiLaunchParameters([$this->toSnakeCase(LtiCustomSettings::PARAM_DRY_RUN) => $actual]),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsAllItemsEnabledReturnsValueAccordingToLtiLaunchParameters($expected, $actual): void
    {
        $this->assertFalse(
            $this->createSubject()->isAllItemsEnabled(
                $this->createLtiLaunchParameters([]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isAllItemsEnabled(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_ALL_ITEMS => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isAllItemsEnabled(
                $this->createLtiLaunchParameters([$this->toSnakeCase(LtiCustomSettings::PARAM_ALL_ITEMS) => $actual]),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testAutoReviewModeEnabledReturnsValueAccordingToLtiLaunchParameters($expected, $actual): void
    {
        $this->assertFalse(
            $this->createSubject()->isReviewModeEnabled(
                $this->createLtiLaunchParameters([]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isAutoReviewModeEnabled(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_AUTO_REVIEW_MODE => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isAutoReviewModeEnabled(
                $this->createLtiLaunchParameters(
                    [$this->toSnakeCase(LtiCustomSettings::PARAM_AUTO_REVIEW_MODE) => $actual],
                ),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsReviewModeEnabledReturnsValueAccordingToLtiLaunchParameters($expected, $actual): void
    {
        $this->assertFalse(
            $this->createSubject()->isReviewModeEnabled(
                $this->createLtiLaunchParameters([]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeEnabled(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_REVIEW_MODE => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeEnabled(
                $this->createLtiLaunchParameters([$this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE) => $actual]),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsReviewModeWithCorrectAnswersReturnsValueAccordingToLtiLaunchParameters(
        $expected,
        $actual,
    ): void {
        $this->assertFalse(
            $this->createSubject()->isReviewModeWithCorrectAnswer(
                $this->createLtiLaunchParameters([]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeWithCorrectAnswer(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeWithCorrectAnswer(
                $this->createLtiLaunchParameters(
                    [$this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_CORRECT) => $actual],
                ),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsReviewModeWithCScoreReturnsValueAccordingToLtiLaunchParameters($expected, $actual): void
    {
        $this->assertFalse($this->createSubject()->isReviewModeWithScore($this->createLtiLaunchParameters([])));

        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeWithScore(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_SCORE => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeWithScore(
                $this->createLtiLaunchParameters(
                    [$this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_SCORE) => $actual],
                ),
            ),
        );
    }

    public function testIsReviewModeWithQuestionReturnsValueAccordingToLtiLaunchParameters(): void
    {
        $this->assertFalse(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION => false,
                ]),
            ),
        );
        $this->assertFalse(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    $this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION) => false,
                ]),
            ),
        );
        $this->assertFalse(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION => 'false',
                ]),
            ),
        );
        $this->assertFalse(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    $this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION) => 'false',
                ]),
            ),
        );

        $this->assertTrue(
            $this->createSubject()->isShowQuestionEnabledForReview(),
        );

        $this->assertTrue(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION => true,
                ]),
            ),
        );
        $this->assertTrue(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    $this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION) => true,
                ]),
            ),
        );

        $this->assertTrue(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION => 'true',
                ]),
            ),
        );
        $this->assertTrue(
            $this->createSubject()->isShowQuestionEnabledForReview(
                $this->createLtiLaunchParameters([
                    $this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_SHOW_QUESTION) => 'true',
                ]),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsReviewModeAllInOneEnabledReturnsValueAccordingToLtiLaunchParameters($expected, $actual): void
    {
        $this->assertFalse($this->createSubject()->isReviewModeAllInOneEnabled($this->createLtiLaunchParameters([])));

        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeAllInOneEnabled(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_REVIEW_MODE_ALL_IN_ONE => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isReviewModeAllInOneEnabled(
                $this->createLtiLaunchParameters(
                    [$this->toSnakeCase(LtiCustomSettings::PARAM_REVIEW_MODE_ALL_IN_ONE) => $actual],
                ),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsMonitoringEnabledReturnsValueAccordingToLtiLaunchParameters($expected, $actual)
    {
        $this->assertFalse($this->createSubject()->isMonitoringEnabled($this->createLtiLaunchParameters([])));
        $this->assertEquals(
            $expected,
            $this->createSubject()->isMonitoringEnabled(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_ENABLE_MONITORING => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isMonitoringEnabled(
                $this->createLtiLaunchParameters([$this->toSnakeCase(LtiCustomSettings::PARAM_ENABLE_MONITORING) => $actual]),
            ),
        );
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsProctorAuthorizationRequiredReturnsValueAccordingToLtiLaunchParameters($expected, $actual)
    {
        $this->assertFalse($this->createSubject()->isProctorAuthorizationRequired($this->createLtiLaunchParameters([])));
        $this->assertEquals(
            $expected,
            $this->createSubject()->isProctorAuthorizationRequired(
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_REQUIRE_PROCTOR_AUTHORIZATION => $actual]),
            ),
        );
        $this->assertEquals(
            $expected,
            $this->createSubject()->isProctorAuthorizationRequired(
                $this->createLtiLaunchParameters(
                    [$this->toSnakeCase(LtiCustomSettings::PARAM_REQUIRE_PROCTOR_AUTHORIZATION) => $actual],
                ),
            ),
        );
    }

    /**
     * @dataProvider pluginsSettingsDataProvider
     */
    public function testGetPluginSettings(mixed $input, mixed $expected = []): void
    {
        $this->assertSame(
            $expected,
            $this->createSubject()->getPluginSettings(['custom' => [LtiCustomSettings::PARAM_PLUGINS => $input]]),
        );
        $this->assertSame(
            $expected,
            $this->createSubject()->getPluginSettings(
                ['custom' => [$this->toSnakeCase(LtiCustomSettings::PARAM_PLUGINS) => $input]],
            ),
        );
    }

    public function pluginsSettingsDataProvider(): array
    {
        return [
            'No settings' => [null],
            'Boolean false' => [json_encode(false)],
            'Boolean true' => [json_encode(true)],
            'Integer' => [json_encode(1)],
            'Decimal' => [json_encode(0.5)],
            'Invalid JSON' => ['{'],
            'Valid settings' => [
                json_encode(['plugin' => ['setting_1' => 'option_1']]),
                ['plugin' => ['setting_1' => 'option_1']],
            ],
        ];
    }

    /**
     * @dataProvider pluginsProvider
     */
    public function testGetAddPluginsToAdd(mixed $input, mixed $expected = []): void
    {
        $this->assertSame(
            $expected,
            $this->createSubject()->getPluginsToAdd(['custom' => [LtiCustomSettings::PARAM_ADD_PLUGINS => $input]]),
        );
        $this->assertSame(
            $expected,
            $this->createSubject()->getPluginsToAdd(
                ['custom' => [$this->toSnakeCase(LtiCustomSettings::PARAM_ADD_PLUGINS) => $input]],
            ),
        );
    }

    /**
     * @dataProvider pluginsProvider
     */
    public function testGetAddPluginsToRemove(mixed $input, mixed $expected = []): void
    {
        $this->assertSame(
            $expected,
            $this->createSubject()->getPluginsToRemove(['custom' => [LtiCustomSettings::PARAM_REMOVE_PLUGINS => $input]]),
        );
        $this->assertSame(
            $expected,
            $this->createSubject()->getPluginsToRemove(
                ['custom' => [$this->toSnakeCase(LtiCustomSettings::PARAM_REMOVE_PLUGINS) => $input]],
            ),
        );
    }

    public function pluginsProvider(): array
    {
        return [
            'No plugins' => [''],
            'Single plugin' => ['test-runner/plugins/plugin1.js', ['test-runner/plugins/plugin1.js']],
            'Single plugin with spaces' => [' test-runner/plugins/plugin1.js  ', ['test-runner/plugins/plugin1.js']],
            'Multiple plugins' => ['test-runner/plugins/plugin1.js,test-runner/plugins/plugin2.js', ['test-runner/plugins/plugin1.js', 'test-runner/plugins/plugin2.js']],
            'Multiple plugins with spaces' => [' test-runner/plugins/plugin1.js  ,   test-runner/plugins/plugin2.js  ', ['test-runner/plugins/plugin1.js', 'test-runner/plugins/plugin2.js']],
        ];
    }

    /**
     * @dataProvider optionBoolDataProvider
     */
    public function testIsReadAloudEnabledReturnsValueAccordingToLtiLaunchParameters(bool $expected, $actual)
    {
        $actual = true === $actual || 'true' === $actual || 'yes' === $actual;

        $result = $this->createSubject()->isReadAloudEnabled(
            $this->createLtiLaunchParameters([
                LtiCustomSettings::PARAM_PLUGINS => json_encode([
                    'readAloud' => [
                        'readAloudOption' => $actual ? LtiCustomSettings::PARAM_READ_ALOUD_OPTION_ENABLED : LtiCustomSettings::PARAM_READ_ALOUD_OPTION_DISABLED,
                    ],
                ]),
            ]),
        );

        $this->assertEquals($expected, $result);

        $this->assertFalse($this->createSubject()->isReadAloudEnabled($this->createLtiLaunchParameters([])));
    }

    /**
     * @dataProvider getProvideDataForClosureClaims
     */
    public function testGetCloseAt(array $ltiLaunchParameters, ?DateTimeInterface $expected): void
    {
        $this->assertEquals($expected, $this->createSubject()->getCloseAt($ltiLaunchParameters));
        $this->assertEquals($expected, $this->createSubject()->getCloseAt($this->allToSnakeCase($ltiLaunchParameters)));
    }

    public function getProvideDataForClosureClaims(): array
    {
        return [
            'claims are not provided' => [
                [],
                null,
            ],
            'TTL is provided' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TTL => 100]),
                Carbon::createFromFormat(
                    DateTimeInterface::RFC3339,
                    self::NOW,
                )->addSeconds(100),
            ],
            'CloseOn is provided' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_CLOSE_ON => self::NOW]),
                Carbon::createFromFormat(
                    DateTimeInterface::RFC3339,
                    self::NOW,
                ),
            ],
            'Both claims are provided' => [
                $this->createLtiLaunchParameters(
                    [
                        LtiCustomSettings::PARAM_CLOSE_ON => '2021-01-01T01:00:00Z',
                        LtiCustomSettings::PARAM_TTL => 4000,
                    ],
                ),
                Carbon::createFromFormat(
                    DateTimeInterface::RFC3339,
                    '2021-01-01T01:00:00Z',
                ),
            ],
            'Both claims are provided, the second case' => [
                $this->createLtiLaunchParameters(
                    [
                        LtiCustomSettings::PARAM_CLOSE_ON => '2021-01-01T02:00:00Z',
                        LtiCustomSettings::PARAM_TTL => 3600,
                    ],
                ),
                Carbon::createFromFormat(
                    DateTimeInterface::RFC3339,
                    '2021-01-01T01:00:00Z',
                ),
            ],
        ];
    }

    /**
     * @dataProvider provideDataForStartsAtTimeTest
     */
    public function testGetStartStartsAt(array $ltiLaunchParameters, ?Carbon $expected = null): void
    {
        $startsAt = $this->createSubject()->getStartsAt($ltiLaunchParameters);
        $this->assertEquals($startsAt, $this->createSubject()->getStartsAt($this->allToSnakeCase($ltiLaunchParameters)));

        if ($expected === null) {
            $this->assertNull($startsAt);
        } else {
            $this->assertTrue($startsAt->equalTo($expected));
        }
    }

    public function provideDataForStartsAtTimeTest(): array
    {
        return [
            'RFC399 time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                ]),
                new Carbon(self::NOW),
            ],
            'ISO8601 time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->toIso8601String(),
                ]),
                new Carbon(self::NOW),
            ],
            'No start at time' => [
                $this->createLtiLaunchParameters([]),
            ],
            'Empty time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => '',
                ]),
            ],
            'Invalid time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => 'not time at all',
                ]),
            ],
        ];
    }

    /**
     * @dataProvider provideDataForEndsAtTimeTest
     */
    public function testGetEndsAt(array $ltiLaunchParameters, ?Carbon $expected = null): void
    {
        $endsAt = $this->createSubject()->getEndsAt($ltiLaunchParameters);
        $this->assertEquals($endsAt, $this->createSubject()->getEndsAt($this->allToSnakeCase($ltiLaunchParameters)));

        if ($expected === null) {
            $this->assertNull($endsAt);
        } else {
            $this->assertTrue($endsAt->equalTo($expected));
        }
    }

    public function provideDataForEndsAtTimeTest(): array
    {
        return [
            'RFC399 time ahead of starts-at' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                ]),
                (new Carbon(self::NOW))->addHour(),
            ],
            'ISO8601 time ahead of starts-at' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->toIso8601String(),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->addHour()->toIso8601String(),
                ]),
                (new Carbon(self::NOW))->addHour(),
            ],
            'No ends-at time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                ]),
            ],
            'Empty ends-at time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => '',
                ]),
            ],
            'Invalid ends-at time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => 'not time at all',
                ]),
            ],
            'No starts-at time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                ]),
            ],
            'Starts-at is ahead of ends-at time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                ]),
            ],
            'Starts-at is equal to ends-at time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                    LtiCustomSettings::PARAM_ENDS_AT => (new Carbon(self::NOW))->format(DATE_ATOM),
                ]),
            ],
        ];
    }

    /**
     * @dataProvider provideDataForStartRemainingWaitTimeTest
     */
    public function testGetStartRemainingWaitTime(array $ltiLaunchParameters, int $expected = 0): void
    {
        $this->assertEquals($expected, $this->createSubject()->getStartRemainingWaitTime($ltiLaunchParameters));
        $this->assertEquals(
            $expected,
            $this->createSubject()->getStartRemainingWaitTime($this->allToSnakeCase($ltiLaunchParameters)),
        );
    }

    public function provideDataForStartRemainingWaitTimeTest(): array
    {
        return [
            'RFC399 time 1h ahead' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->format(DATE_ATOM),
                ]),
                60 * 60 * 1000,
            ],
            'RFC399 time 1h behind' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->subHour()->format(DATE_ATOM),
                ]),
            ],
            'ISO8601 time 1h ahead' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->addHour()->toIso8601String(),
                ]),
                60 * 60 * 1000,
            ],
            'ISO8601 time 1h behind' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => (new Carbon(self::NOW))->subHour()->toIso8601String(),
                ]),
            ],
            'No start at time' => [
                $this->createLtiLaunchParameters([]),
            ],
            'Empty time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => '',
                ]),
            ],
            'Invalid time' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_STARTS_AT => 'not time at all',
                ]),
            ],
        ];
    }

    /**
     * @dataProvider getProvideDataForTestTitle
     */
    public function testGetTestTitle(array $ltiLaunchParameters, ?string $expected = null): void
    {
        $this->assertSame($expected, $this->createSubject()->getTestTitle($ltiLaunchParameters));
        $this->assertSame($expected, $this->createSubject()->getTestTitle($this->allToSnakeCase($ltiLaunchParameters)));
    }

    public function getProvideDataForTestTitle(): array
    {
        return [
            'with test title' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_TEST_TITLE => 'test',
                ]),
                'test',
            ],
            'with empty test title' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_TEST_TITLE => '',
                ]),
            ],
            'with no test title' => [
                $this->createLtiLaunchParameters([]),
            ],
        ];
    }

    /**
     * @dataProvider getProvideDataForCustomTitlesClaims
     */
    public function testGetCustomTitles(array $ltiLaunchParameters, ?array $expected): void
    {
        $this->assertEquals($expected, $this->createSubject()->getCustomTitles($ltiLaunchParameters));
        $this->assertEquals(
            $expected,
            $this->createSubject()->getCustomTitles($this->allToSnakeCase($ltiLaunchParameters)),
        );
    }

    public function getProvideDataForCustomTitlesClaims(): array
    {
        $titles = [
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

        return [
            'claims are not provided' => [
                [],
                null,
            ],
            'empty titles are provided' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => "[]"]),
                [],
            ],
            'custom titles are provided' => [
                $this->createLtiLaunchParameters(
                    [
                        LtiCustomSettings::PARAM_TITLES => json_encode($titles),
                    ],
                ),
                $titles,
            ],
            'invalid custom titles are provided' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => 1]),
                null,
            ],
            'invalid custom titles are provided 2' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => "customTest"]),
                null,
            ],
            'invalid custom titles are provided 3' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => true]),
                null,
            ],
            'invalid custom titles are provided 4' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => []]),
                null,
            ],
            'invalid custom titles are provided 5' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => null]),
                null,
            ],
            'invalid custom titles are provided 6' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => "[\"invalid json-encoded string"]),
                null,
            ],
        ];
    }

    /**
     * @dataProvider getProvideDataForValidationClaims
     */
    public function testItCanValidateClaims(array $ltiLaunchParameters, ?string $expectedException): void
    {
        if (null !== $expectedException) {
            $this->expectException(LtiCustomSettingsException::class);
        } else {
            $this->assertTrue(true);
        }

        $this->createSubject()->validateClaims($ltiLaunchParameters);
        $this->createSubject()->validateClaims($this->allToSnakeCase($ltiLaunchParameters));
    }

    public function getProvideDataForValidationClaims(): array
    {
        return [
            'claims are not provided' => [
                [],
                null,
            ],
            'empty titles are provided' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => "[]"]),
                null,
            ],
            'custom titles are provided' => [
                $this->createLtiLaunchParameters(
                    [
                        LtiCustomSettings::PARAM_TITLES =>
                            '[{"type":"test", "label":"Custom test title value"}, {"type":"section"}, {"type":"item"}]',
                    ],
                ),
                null,
            ],
            'invalid custom titles are provided' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => 1]),
                LtiCustomSettingsException::class,
            ],
            'invalid custom titles are provided 2' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => "customTest"]),
                LtiCustomSettingsException::class,
            ],
            'invalid custom titles are provided 3' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => true]),
                LtiCustomSettingsException::class,
            ],
            'invalid custom titles are provided 4' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => []]),
                LtiCustomSettingsException::class,
            ],
            'invalid custom titles are provided 5' => [
                $this->createLtiLaunchParameters([LtiCustomSettings::PARAM_TITLES => "[\"invalid json-encoded string"]),
                LtiCustomSettingsException::class,
            ],
        ];
    }

    /**
     * @dataProvider getPluginParametersFromLTIParametersDataProvider
     */
    public function testGetPluginParametersFromLTIParameters(array $expected, array $settings): void
    {
        $parameters = $this->createLtiLaunchParameters($settings);

        $default = array_fill_keys(
            [
                'isForceFullScreenPresent',
                'isForceFullScreenEnabled',
                'isPauseOnBlurPresent',
                'isPauseOnBlurEnabled',
                'isPreventScreenshotEnabled',
                'isPreventScreenshotPresent',
                'isDisableCommandsEnabled',
                'isDisableCommandsPresent',
            ],
            false,
        ) + array_fill_keys(
            [
                'isForceFullScreenAutoresumeEnabled',
                'isPauseOnBlurAutoresumeEnabled',
            ],
            true,
        );

        $this->assertEquals(
            $expected + $default,
            [
                'isForceFullScreenPresent' => $this->createSubject()->isForceFullScreenPresent($parameters),
                'isForceFullScreenEnabled' => $this->createSubject()->isForceFullScreenEnabled($parameters),
                'isForceFullScreenAutoresumeEnabled' => $this->createSubject()->isForceFullScreenAutoresumeEnabled($parameters),
                'isPauseOnBlurPresent' => $this->createSubject()->isPauseOnBlurPresent($parameters),
                'isPauseOnBlurEnabled' => $this->createSubject()->isPauseOnBlurEnabled($parameters),
                'isPauseOnBlurAutoresumeEnabled' => $this->createSubject()->isPauseOnBlurAutoresumeEnabled($parameters),
                'isPreventScreenshotEnabled' => $this->createSubject()->isPreventScreenshotEnabled($parameters),
                'isPreventScreenshotPresent' => $this->createSubject()->isPreventScreenshotPresent($parameters),
                'isDisableCommandsEnabled' => $this->createSubject()->isDisableCommandsEnabled($parameters),
                'isDisableCommandsPresent' => $this->createSubject()->isDisableCommandsPresent($parameters),
            ],
        );
        $parameters = $this->allToSnakeCase($parameters);
        $this->assertEquals(
            $expected + $default,
            [
                'isForceFullScreenPresent' => $this->createSubject()->isForceFullScreenPresent($parameters),
                'isForceFullScreenEnabled' => $this->createSubject()->isForceFullScreenEnabled($parameters),
                'isForceFullScreenAutoresumeEnabled' => $this->createSubject()->isForceFullScreenAutoresumeEnabled($parameters),
                'isPauseOnBlurPresent' => $this->createSubject()->isPauseOnBlurPresent($parameters),
                'isPauseOnBlurEnabled' => $this->createSubject()->isPauseOnBlurEnabled($parameters),
                'isPauseOnBlurAutoresumeEnabled' => $this->createSubject()->isPauseOnBlurAutoresumeEnabled($parameters),
                'isPreventScreenshotEnabled' => $this->createSubject()->isPreventScreenshotEnabled($parameters),
                'isPreventScreenshotPresent' => $this->createSubject()->isPreventScreenshotPresent($parameters),
                'isDisableCommandsEnabled' => $this->createSubject()->isDisableCommandsEnabled($parameters),
                'isDisableCommandsPresent' => $this->createSubject()->isDisableCommandsPresent($parameters),
            ],
        );
    }

    private function getPluginParametersFromLTIParametersDataProvider(): array
    {
        return [
            'none set' => [
                'expected' => [],
                'parameters' => [],
            ],
            'deliverySettings.plugins.forceFullScreen.enabled=false' => [
                'expected' => ['isForceFullScreenPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => false],
            ],
            'deliverySettings.plugins.forceFullScreen.enabled=""' => [
                'expected' => ['isForceFullScreenPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => ''],
            ],
            'deliverySettings.plugins.forceFullScreen.enabled="false' => [
                'expected' => ['isForceFullScreenPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => 'false'],
            ],
            'deliverySettings.plugins.forceFullScreen.enabled=true' => [
                'expected' => ['isForceFullScreenPresent' => true,  'isForceFullScreenEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => true],
            ],
            'deliverySettings.plugins.forceFullScreen.enabled="true"' => [
                'expected' => ['isForceFullScreenPresent' => true, 'isForceFullScreenEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_ENABLED => 'true'],
            ],
            'deliverySettings.plugins.forceFullScreen.autoresume=false' => [
                'expected' => ['isForceFullScreenAutoresumeEnabled' => false],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME => false],
            ],
            'deliverySettings.plugins.forceFullScreen.autoresume=""' => [
                'expected' => ['isForceFullScreenAutoresumeEnabled' => false],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME => ''],
            ],
            'deliverySettings.plugins.forceFullScreen.autoresume="false' => [
                'expected' => ['isForceFullScreenAutoresumeEnabled' => false],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME => 'false'],
            ],
            'deliverySettings.plugins.forceFullScreen.autoresume=true' => [
                'expected' => ['isForceFullScreenAutoresumeEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME => true],
            ],
            'deliverySettings.plugins.forceFullScreen.autoresume="true"' => [
                'expected' => ['isForceFullScreenAutoresumeEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_FORCE_FULLSCREEN_AUTORESUME => 'true'],
            ],
            'deliverySettings.plugins.pauseOnBlur.enabled=false' => [
                'expected' => ['isPauseOnBlurPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => false],
            ],
            'deliverySettings.plugins.pauseOnBlur.enabled=""' => [
                'expected' => ['isPauseOnBlurPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => ''],
            ],
            'deliverySettings.plugins.pauseOnBlur.enabled="false' => [
                'expected' => ['isPauseOnBlurPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => 'false'],
            ],
            'deliverySettings.plugins.pauseOnBlur.enabled=true' => [
                'expected' => ['isPauseOnBlurPresent' => true, 'isPauseOnBlurEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => true],
            ],
            'deliverySettings.plugins.pauseOnBlur.enabled="true"' => [
                'expected' => ['isPauseOnBlurPresent' => true, 'isPauseOnBlurEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_ENABLED => 'true'],
            ],
            'deliverySettings.plugins.pauseOnBlur.autoresume=false' => [
                'expected' => ['isPauseOnBlurAutoresumeEnabled' => false],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME => false],
            ],
            'deliverySettings.plugins.pauseOnBlur.autoresume=""' => [
                'expected' => ['isPauseOnBlurAutoresumeEnabled' => false],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME => ''],
            ],
            'deliverySettings.plugins.pauseOnBlur.autoresume="false' => [
                'expected' => ['isPauseOnBlurAutoresumeEnabled' => false],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME => 'false'],
            ],
            'deliverySettings.plugins.pauseOnBlur.autoresume=true' => [
                'expected' => ['isPauseOnBlurAutoresumeEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME => true],
            ],
            'deliverySettings.plugins.pauseOnBlur.autoresume="true"' => [
                'expected' => ['isPauseOnBlurAutoresumeEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PAUSE_ON_BLUR_AUTORESUME => 'true'],
            ],
            'deliverySettings.plugins.preventScreenshot.enabled=false' => [
                'expected' => ['isPreventScreenshotPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => false],
            ],
            'deliverySettings.plugins.preventScreenshot.enabled=""' => [
                'expected' => ['isPreventScreenshotPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => ''],
            ],
            'deliverySettings.plugins.preventScreenshot.enabled="false' => [
                'expected' => ['isPreventScreenshotPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => 'false'],
            ],
            'deliverySettings.plugins.preventScreenshot.enabled=true' => [
                'expected' => ['isPreventScreenshotPresent' => true, 'isPreventScreenshotEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => true],
            ],
            'deliverySettings.plugins.preventScreenshot.enabled="true"' => [
                'expected' => ['isPreventScreenshotPresent' => true, 'isPreventScreenshotEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_PREVENT_SCREENSHOT_ENABLED => 'true'],
            ],
            'deliverySettings.plugins.disableCommands.enabled=false' => [
                'expected' => ['isDisableCommandsPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => false],
            ],
            'deliverySettings.plugins.disableCommands.enabled=""' => [
                'expected' => ['isDisableCommandsPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => ''],
            ],
            'deliverySettings.plugins.disableCommands.enabled="false' => [
                'expected' => ['isDisableCommandsPresent' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => 'false'],
            ],
            'deliverySettings.plugins.disableCommands.enabled=true' => [
                'expected' => ['isDisableCommandsPresent' => true, 'isDisableCommandsEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => true],
            ],
            'deliverySettings.plugins.disableCommands.enabled="true"' => [
                'expected' => ['isDisableCommandsPresent' => true, 'isDisableCommandsEnabled' => true],
                'parameters' => [LtiCustomSettings::PARAM_PLUGIN_DISABLE_COMMANDS_ENABLED => 'true'],
            ],
        ];
    }

    /**
     * @dataProvider pluginsSettingsDataProvider
     */
    public function testGetItemRunnerConfigElements(mixed $input, mixed $expected = []): void
    {
        $this->assertSame(
            $expected,
            $this->createSubject()->getItemRunnerConfigElements(
                ['custom' => [LtiCustomSettings::PARAM_ITEM_RUNNER_CONFIG_ELEMENTS => $input]],
            ),
        );
        $this->assertSame(
            $expected,
            $this->createSubject()->getItemRunnerConfigElements(
                ['custom' => [$this->toSnakeCase(LtiCustomSettings::PARAM_ITEM_RUNNER_CONFIG_ELEMENTS) => $input]],
            ),
        );
    }

    public function itemRunnerConfigElementsDataProvider(): array
    {
        return [
            'No data' => [null],
            'Boolean false' => [json_encode(false)],
            'Boolean true' => [json_encode(true)],
            'Integer' => [json_encode(1)],
            'Decimal' => [json_encode(0.5)],
            'Invalid JSON' => ['{'],
            'Valid data' => [
                json_encode(['ExtendedTextInteraction' => ['propertyOverride' => ['uploadTimeout' => 10]]]),
                ['ExtendedTextInteraction' => ['propertyOverride' => ['uploadTimeout' => 10]]],
            ],
        ];
    }

    /**
     * @dataProvider provideDataForTestGetAttemptId
     */
    public function testGetAttemptId(array $ltiLaunchParameters, ?string $expected = null): void
    {
        $this->assertSame($expected, $this->createSubject()->getAttemptId($ltiLaunchParameters));
        $this->assertSame($expected, $this->createSubject()->getAttemptId($this->allToSnakeCase($ltiLaunchParameters)));
    }

    public function provideDataForTestGetAttemptId(): array
    {
        return [
            'with attempt ID' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_ATTEMPT_ID => 'test',
                ]),
                'test',
            ],
            'with empty attempt ID' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_ATTEMPT_ID => '',
                ]),
            ],
            'with no attempt ID' => [
                $this->createLtiLaunchParameters([]),
            ],
            'with 0 attempt ID' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_ATTEMPT_ID => '0',
                ]),
                '0',
            ],
        ];
    }

    /**
     * @dataProvider provideDataForTestGetDeliveryExecutionIdId
     */
    public function testGetDeliveryExecutionIdId(array $ltiLaunchParameters, ?string $expected = null): void
    {
        $this->assertSame($expected, $this->createSubject()->getReviewDeliveryExecutionId($ltiLaunchParameters));
        $this->assertSame(
            $expected,
            $this->createSubject()->getReviewDeliveryExecutionId($this->allToSnakeCase($ltiLaunchParameters)),
        );
    }

    public function provideDataForTestGetDeliveryExecutionIdId(): array
    {
        return [
            'with delivery execution ID' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_DELIVERY_EXECUTION_ID => 'test',
                ]),
                'test',
            ],
            'with empty delivery execution ID' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_DELIVERY_EXECUTION_ID => '',
                ]),
            ],
            'with no delivery execution ID' => [
                $this->createLtiLaunchParameters([]),
            ],
            'with 0 delivery execution ID' => [
                $this->createLtiLaunchParameters([
                    LtiCustomSettings::PARAM_REVIEW_DELIVERY_EXECUTION_ID => '0',
                ]),
                '0',
            ],
        ];
    }

    /**
     * @dataProvider provideHasCustomNavigationModeData
     */
    public function testHasCustomNavigationMode(array $ltiLaunchParameters, bool $expected): void
    {
        self::assertSame(
            $expected,
            $this->createSubject()->hasCustomNavigationMode($ltiLaunchParameters),
        );
        self::assertSame(
            $expected,
            $this->createSubject()->hasCustomNavigationMode($this->allToSnakeCase($ltiLaunchParameters)),
        );
    }

    public function provideHasCustomNavigationModeData(): array
    {
        return [
            [
                [],
                false,
            ],
            [
                ['foo'],
                false,
            ],
            [
                [
                    'foo' => 'bar',
                ],
                false,
            ],
            [
                [
                    'custom' => [
                        'deliverySettings.navigation' => true,
                    ],
                ],
                true,
            ],
        ];
    }

    public function testGetExtraTime(): void
    {
        $ltiLaunchParameters = $this->createLtiLaunchParameters([
            LtiCustomSettings::PARAM_EXTRA_TIME => 100,
        ]);

        $this->assertEquals(100, $this->createSubject()->getExtraTime($ltiLaunchParameters));
        $this->assertEquals(100, $this->createSubject()->getExtraTime($this->allToSnakeCase($ltiLaunchParameters)));
    }

    public function testGetBatteryDeliveryId(): void
    {
        $this->assertNull($this->createSubject()->getBatteryDeliveryId());
        $this->assertNull($this->createSubject()->getBatteryDeliveryId([
            LtiCustomSettings::PARAM_BATTERY_DELIVERY_ID => '',
        ]));
        $ltiLaunchParameters = $this->createLtiLaunchParameters([
            LtiCustomSettings::PARAM_BATTERY_DELIVERY_ID => 'deliveryId',
        ]);

        $this->assertEquals('deliveryId', $this->createSubject()->getBatteryDeliveryId($ltiLaunchParameters));
        $this->assertEquals('deliveryId', $this->createSubject()->getBatteryDeliveryId($this->allToSnakeCase($ltiLaunchParameters)));
    }

    private function createLtiLaunchParameters(array $params): array
    {
        return ['custom' => $params];
    }

    public function optionBoolDataProvider(): array
    {
        return [
            'empty string returns false' => [false, ''],
            'boolean true returns true' => [true, true],
            'string true returns true' => [true, 'true'],
            'boolean false returns false' => [false, false],
            'string false returns false' => [false, 'false'],
            'string yes returns true' => [true, 'yes'],
            'string no returns false' => [false, 'no'],
            'random string returns false' => [false, 'some other parameter'],
        ];
    }

    private function allToSnakeCase(array $values): array
    {
        if (empty($values['custom'])) {
            return $values;
        }
        $customParameters = [];
        foreach ($values['custom'] as $key => $value) {
            $customParameters[$this->toSnakeCase($key)] = $value;
        }
        $values['custom'] = $customParameters;

        return $values;
    }

    private function toSnakeCase(string $value): string
    {
        return str_replace('.', '_', strtolower($value));
    }

    private function createSubject(): LtiCustomSettings
    {
        return new LtiCustomSettings(
            $this->getContainer()->get(LtiTokenResolver::class),
        );
    }
}
