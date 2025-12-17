<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Sender;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Sender\LtiBasicOutcomeSender;
use App\Messenger\Message\ResultExtractionMessage;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementLtiClient\Client\LtiBasicOutcomeClientInterface;
use OAT\Library\EnvironmentManagementLtiClient\Exception\LtiBasicOutcomeClientException;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Twig\Environment;

class LtiBasicOutcomeSenderTest extends TestCase
{
    private LtiBasicOutcomeSender $subject;
    private RegistrationRepositoryInterface|MockObject $registrationRepositoryMock;
    private LtiBasicOutcomeClientInterface|MockObject $ltiBasicOutcomeClientMock;
    private Environment|MockObject $twigMock;
    private LoggerInterface|MockObject $loggerMock;

    protected function setUp(): void
    {
        $this->registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $this->ltiBasicOutcomeClientMock = $this->createMock(LtiBasicOutcomeClientInterface::class);
        $this->twigMock = $this->createMock(Environment::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject = new LtiBasicOutcomeSender(
            $this->registrationRepositoryMock,
            $this->ltiBasicOutcomeClientMock,
            $this->createMock(LtiCustomSettings::class),
            $this->twigMock,
            $this->loggerMock,
        );
    }

    public function testSend(): void
    {
        $resultExtractionMessageMock = $this->createMock(ResultExtractionMessage::class);
        $resultExtractionMessageMock
            ->method('getId')
            ->willReturn('messageId');

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->method('getResultId')
            ->willReturn('deliveryExecutionResultId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn([
                'lis_outcome_service_url' => 'https://outcome_service.url/foo/bar',
                'oauth_consumer_key' => 'key',
                'platform_issuer' => 'https://platform.issuer',
                'client_id' => 'clientId',
            ]);

        $this->loggerMock
            ->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['[deliveryExecutionId] Url was validated, URL: https://outcome_service.url/foo/bar'],
                ['[deliveryExecutionId] LTI replace result request was done to https://outcome_service.url/foo/bar'],
            );

        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with(
                'Ims/Result/deliveryExecutionResult.xml.twig',
                [
                    'messageIdentifier' => 'messageId',
                    'lisResultSourcedId' => 'deliveryExecutionResultId',
                    'score' => 1.0,
                ],
            )
            ->willReturn('xmlContent');

        $registrationMock = $this->createMock(RegistrationInterface::class);
        $registrationMock
            ->method('getIdentifier')
            ->willReturn('registrationId');

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->with('https://outcome_service.url', 'clientId')
            ->willReturn($registrationMock);

        $this->ltiBasicOutcomeClientMock
            ->expects($this->once())
            ->method('sendBasicOutcome')
            ->with('registrationId', 'https://outcome_service.url/foo/bar', 'xmlContent');

        $this->subject->send($deliveryExecutionMock, ['score' => 1.0], $resultExtractionMessageMock);
    }

    /**
     * @dataProvider emptyLtiParameterProvider
     */
    public function testSendWhenAnLtiParameterIsEmpty(array $ltiLaunchParameters): void
    {
        $resultExtractionMessageMock = $this->createMock(ResultExtractionMessage::class);
        $resultExtractionMessageMock
            ->method('getId')
            ->willReturn('messageId');

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->method('getResultId')
            ->willReturn('deliveryExecutionResultId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn($ltiLaunchParameters);

        $this->loggerMock
            ->expects($this->never())
            ->method('info');


        $this->subject->send($deliveryExecutionMock, ['score' => 1.0], $resultExtractionMessageMock);
    }

    // Returns those cases of LtiParameters when BasicOutcomeSender shouldn't send any basic outcome
    public function emptyLtiParameterProvider(): array
    {
        return [
            'lis_outcome_service_url is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => 'x', 'platform_issuer' => 'x', 'client_id' => 'x']],
            'lis_outcome_service_url and oauth_consumer_key is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => '', 'platform_issuer' => 'x', 'client_id' => 'x']],
            'lis_outcome_service_url and platform_issuer is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => 'x', 'platform_issuer' => '', 'client_id' => 'x']],
            'lis_outcome_service_url and client_id is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => 'x', 'platform_issuer' => 'x', 'client_id' => '']],
            'oauth_consumer_key and platform_issuer is empty' => [['lis_outcome_service_url' => 'x', 'oauth_consumer_key' => '', 'platform_issuer' => '', 'client_id' => 'x']],
            'oauth_consumer_key and client_id is empty' => [['lis_outcome_service_url' => 'x', 'oauth_consumer_key' => '', 'platform_issuer' => 'x', 'client_id' => '']],
            'lis_outcome_service_url, oauth_consumer_key and platform_issuer is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => '', 'platform_issuer' => '', 'client_id' => 'x']],
            'lis_outcome_service_url, oauth_consumer_key and client_id is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => '', 'platform_issuer' => 'x', 'client_id' => '']],
            'oauth_consumer_key, platform_issuer and client_id is empty' => [['lis_outcome_service_url' => 'x', 'oauth_consumer_key' => '', 'platform_issuer' => '', 'client_id' => '']],
            'lis_outcome_service_url, oauth_consumer_key, platform_issuer and client_id is empty' => [['lis_outcome_service_url' => '', 'oauth_consumer_key' => '', 'platform_issuer' => '', 'client_id' => '']],
        ];
    }

    public function testSendWhenRegistrationLookupFails(): void
    {
        $resultExtractionMessageMock = $this->createMock(ResultExtractionMessage::class);
        $resultExtractionMessageMock
            ->method('getId')
            ->willReturn('messageId');

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->method('getResultId')
            ->willReturn('deliveryExecutionResultId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn([
                'lis_outcome_service_url' => 'https://outcome_service.url/foo/bar',
                'oauth_consumer_key' => 'key',
                'platform_issuer' => 'https://platform.issuer',
                'client_id' => 'clientId',
            ]);

        $this->loggerMock
            ->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['[deliveryExecutionId] Url was validated, URL: https://outcome_service.url/foo/bar'],
                ['[deliveryExecutionId] LTI replace result request was done to https://outcome_service.url/foo/bar'],
            );

        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with(
                'Ims/Result/deliveryExecutionResult.xml.twig',
                [
                    'messageIdentifier' => 'messageId',
                    'lisResultSourcedId' => 'deliveryExecutionResultId',
                    'score' => 1.0,
                ],
            )
            ->willReturn('xmlContent');

        $registrationMock = $this->createMock(RegistrationInterface::class);
        $registrationMock
            ->method('getIdentifier')
            ->willReturn('registrationId');

        $this->registrationRepositoryMock
            ->expects($this->exactly(2))
            ->method('findByPlatformIssuer')
            ->withConsecutive(
                ['https://outcome_service.url', 'clientId'],
                ['https://platform.issuer', 'clientId'],
            )
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new EnvironmentManagementClientException('reason')),
                $registrationMock,
            );

        $this->ltiBasicOutcomeClientMock
            ->expects($this->once())
            ->method('sendBasicOutcome')
            ->with('registrationId', 'https://outcome_service.url/foo/bar', 'xmlContent');

        $this->subject->send($deliveryExecutionMock, ['score' => 1.0], $resultExtractionMessageMock);
    }

    public function testSendWhenRequestFails(): void
    {
        $resultExtractionMessageMock = $this->createMock(ResultExtractionMessage::class);
        $resultExtractionMessageMock
            ->method('getId')
            ->willReturn('messageId');

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->method('getResultId')
            ->willReturn('deliveryExecutionResultId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn([
                'lis_outcome_service_url' => 'https://outcome_service.url/foo/bar',
                'oauth_consumer_key' => 'key',
                'platform_issuer' => 'https://platform.issuer',
                'client_id' => 'clientId',
            ]);

        $this->loggerMock
            ->expects($this->once())
            ->method('info')
            ->with('[deliveryExecutionId] Url was validated, URL: https://outcome_service.url/foo/bar');

        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with(
                'Ims/Result/deliveryExecutionResult.xml.twig',
                [
                    'messageIdentifier' => 'messageId',
                    'lisResultSourcedId' => 'deliveryExecutionResultId',
                    'score' => 1.0,
                ],
            )
            ->willReturn('xmlContent');

        $registrationMock = $this->createMock(RegistrationInterface::class);
        $registrationMock
            ->method('getIdentifier')
            ->willReturn('registrationId');

        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findByPlatformIssuer')
            ->with('https://outcome_service.url', 'clientId')
            ->willReturn($registrationMock);

        $this->ltiBasicOutcomeClientMock
            ->expects($this->once())
            ->method('sendBasicOutcome')
            ->with('registrationId', 'https://outcome_service.url/foo/bar', 'xmlContent')
            ->willThrowException(new LtiBasicOutcomeClientException());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LTI replaceResultRequest failed. Delivery Execution ID: deliveryExecutionId');

        $this->subject->send($deliveryExecutionMock, ['score' => 1.0], $resultExtractionMessageMock);
    }
}
