<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Log;

use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;

class CreateLogActionTest extends WebTestCase
{
    use DocumentTestingTrait;
    use LoggerTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const TEST_URL = '/api/v1/logs';

    /** @var KernelBrowser */
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->setUpTestDocumentManager();
        $this->setUpTestLogHandler();
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testItLogsToTheProperChannel(string $message, string $level, ?string $type = null, mixed $context = null): void
    {
        $this->client->request(
            Request::METHOD_POST,
            self::TEST_URL,
            [],
            [],
            [],
            json_encode([
                [
                    'type' => $type,
                    'message' => $message,
                    'level' => $level,
                    'context' => $context,
                ],
            ]),
        );

        $response = $this->client->getResponse();

        $this->assertHasLogRecordWithMessage($message, $this->getLogLevelNumber($level), $type ?? 'default');
        $this->assertHasRecordThatPasses(static function ($record) use ($message, $context) {
            return
                $record['message']  === $message
                && $record['context']  === ($context ?? []);
        }, $this->getLogLevelNumber($level), $type ?? 'default');

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function validDataProvider(): array
    {
        return [
            ['test message', 'debug', 'audit_platform', null],
            ['test message', 'debug', 'audit_delivery_execution', null],
            ['test message', 'debug', 'default', null],
            ['test message', 'debug', null],
            ['test message', 'info', 'default', null],
            ['test message', 'notice', 'default', null],
            ['test message', 'warning', 'default', null],
            ['test message', 'error', 'default', null],
            ['test message', 'critical', 'default', null],
            ['test message', 'alert', 'default', null],
            ['test message', 'emergency', 'default', null],
            ['test message', 'debug', 'default', ['key' => 'value']],
        ];
    }

    public function testItLogsMultipleEntries(): void
    {

        $this->client->request(
            Request::METHOD_POST,
            self::TEST_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            json_encode([
                [
                    'type' => 'audit_platform',
                    'message' => 'test1',
                    'level' => 'debug',
                    'context' => [],
                ],
                [
                    'type' => 'audit_delivery_execution',
                    'message' => 'test2',
                    'level' => 'debug',
                    'context' => [],
                ],
            ]),
        );

        $response = $this->client->getResponse();

        $this->assertHasLogRecordWithMessage('test1', $this->getLogLevelNumber('debug'), 'audit_platform');
        $this->assertHasLogRecordWithMessage('test2', $this->getLogLevelNumber('debug'), 'audit_delivery_execution');

        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testItFailsWhenParametersInvalid(
        string $expectedErrorMessage,
        ?string $message = null,
        ?string $level = null,
        ?string $type = null,
        $context = null,
    ): void {

        $this->client->request(
            Request::METHOD_POST,
            self::TEST_URL,
            [],
            [],
            [],
            json_encode([
                [
                    'type' => $type,
                    'message' => $message,
                    'level' => $level,
                    'context' => $context,
                ],
            ]),
        );

        $response = $this->client->getResponse();

        $this->assertEquals($expectedErrorMessage, json_decode($response->getContent(), true)['error']['message']);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function invalidDataProvider(): array
    {
        return [
            ['[0][message]: This value should not be blank., [0][level]: This value should not be blank.', null, null, null, null],
            ['[0][level]: This value should not be blank.', 'test message', null, null, null],
            ['[0][level]: The value you selected is not a valid choice.', 'test message', 'invalid level', null, null],
            ['[0][type]: The value you selected is not a valid choice.', 'test message', 'debug', 'invalid channel', null],
            ['[0][context]: This value should be of type array.', 'test message', 'debug', null, 'invalid context'],
        ];
    }

    private function getLogLevelNumber(string $logLevel): int
    {
        $logLevelMap = [
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
        ];

        return $logLevelMap[$logLevel];
    }
}
