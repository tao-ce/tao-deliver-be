<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Action\Lti\DeepLinking;

use App\Action\Lti\DeepLinking\SubmitDeepLinksAction;
use App\DynamicQueryApi\Exception\DynamicQueryApiException;
use App\DynamicQueryApi\Gateway\DynamicQueryApiGateway;
use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\Delivery;
use App\DynamicQueryApi\Model\SearchResponse;
use App\Lti\DeepLinking\Builder\ResourceCollectionBuilder;
use App\Validator\Lti\DeepLinking\SubmitDeepLinksActionRequestValidator;
use DateTime;
use Exception;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\DeepLinkingSettingsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchResponseBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SubmitDeepLinksActionTest extends TestCase
{
    private const BATTERIES = ['battery1', 'battery2'];
    private const DELIVERIES = ['delivery1', 'delivery2'];
    private SubmitDeepLinksActionRequestValidator $validatorMock;
    private DynamicQueryApiGateway $dynamicQueryApiGatewayMock;
    private DeepLinkingLaunchResponseBuilder $deepLinkingLaunchResponseBuilderMock;
    private ResourceCollectionBuilder $resourceCollectionBuilderMock;
    private bool $deepLinkingReturnAutoSubmitFormDummy = false;
    private Request $requestMock;
    private LtiMessagePayloadInterface $ltiMessagePayloadMock;
    private string $tenantIdDummy;
    private SearchResponse $batterySearchResponseMock;
    private SearchResponse $deliverySearchResponseMock;

    protected function setup(): void
    {
        $this->tenantIdDummy = 'foobar';

        $this->validatorMock = $this->createMock(SubmitDeepLinksActionRequestValidator::class);
        $this->dynamicQueryApiGatewayMock = $this->createMock(DynamicQueryApiGateway::class);
        $this->deepLinkingLaunchResponseBuilderMock = $this->createMock(DeepLinkingLaunchResponseBuilder::class);
        $this->resourceCollectionBuilderMock = $this->createMock(ResourceCollectionBuilder::class);
        $this->requestMock = $this->createMock(Request::class);
        $this->ltiMessagePayloadMock = $this->createMock(LtiMessagePayloadInterface::class);
        $this->batterySearchResponseMock = $this->createMock(SearchResponse::class);
        $this->deliverySearchResponseMock = $this->createMock(SearchResponse::class);
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithInvalidSettingsThrowException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings claim is required');
        $this->expectGetValidatedRequestParameter(self::BATTERIES, self::DELIVERIES);
        $this->expectSearchDeliveriesWithIds();

        $this
            ->ltiMessagePayloadMock
            ->expects(self::once())
            ->method('getDeepLinkingSettings')
            ->willReturn(null);

        $sut = $this->getSut();

        $sut($this->requestMock, $this->ltiMessagePayloadMock, $this->tenantIdDummy);
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function testWithBatteryCountMismatchThrowException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid battery id(s) provided: . They may not belong to the given tenant, or they may not exist.');
        $this->expectGetValidatedRequestParameter([]);

        $sut = $this->getSut();

        self::assertEquals(
            new JsonResponse(['url' => 'url']),
            $sut($this->requestMock, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function testWithDeliveryCountMismatchThrowException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid delivery id(s) provided: . They may not belong to the given tenant, or they may not exist.');
        $this->expectGetValidatedRequestParameter(self::BATTERIES, []);
        $this->expectSearchDeliveriesWithIds();

        $sut = $this->getSut();

        self::assertEquals(
            new JsonResponse(['url' => 'url']),
            $sut($this->requestMock, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithBuildError(): void
    {
        $this->deepLinkingReturnAutoSubmitFormDummy = false;

        $this->expectGetValidatedRequestParameter(self::BATTERIES, self::DELIVERIES);
        $this->expectSearchDeliveriesWithIds();
        $this->expectBatterySearchResponse();
        $this->expectDeliverySearchResponse();
        $this->expectGetDeepLinkingSettings();

        $this
            ->deepLinkingLaunchResponseBuilderMock
            ->expects(self::once())
            ->method('buildDeepLinkingLaunchResponse')
            ->willThrowException(new Exception());

        $sut = $this->getSut();

        self::assertEquals(
            new JsonResponse(['url' => '']),
            $sut($this->requestMock, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function testInvokeRedirectUrl(): void
    {
        $this->deepLinkingReturnAutoSubmitFormDummy = false;

        $this->expectGetValidatedRequestParameter(self::BATTERIES, self::DELIVERIES);
        $this->expectSearchDeliveriesWithIds();
        $this->expectBatterySearchResponse();
        $this->expectDeliverySearchResponse();
        $this->expectGetDeepLinkingSettings();
        $this->expectBuildDeepLinkingLaunchResponse('toUrl', 'url');

        $sut = $this->getSut();

        self::assertEquals(
            new JsonResponse(['url' => 'url']),
            $sut($this->requestMock, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws DynamicQueryApiException
     * @throws LtiExceptionInterface
     */
    public function testInvokeAutoSubmitForm(): void
    {
        $this->deepLinkingReturnAutoSubmitFormDummy = true;

        $this->expectGetValidatedRequestParameter(self::BATTERIES, self::DELIVERIES);
        $this->expectSearchDeliveriesWithIds();
        $this->expectBatterySearchResponse();
        $this->expectDeliverySearchResponse();
        $this->expectGetDeepLinkingSettings();
        $this->expectBuildDeepLinkingLaunchResponse('toHtmlRedirectForm', 'html');

        $sut = $this->getSut();

        self::assertEquals(
            new JsonResponse(['html' => 'html']),
            $sut($this->requestMock, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    private function getSut(): SubmitDeepLinksAction
    {
        $registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $registrationRepositoryMock
            ->expects(self::once())
            ->method('findByPlatformIssuer')
            ->with('iss', 'aud')
            ->willReturn($this->createMock(RegistrationInterface::class));

        $this
            ->dynamicQueryApiGatewayMock
            ->expects(self::once())
            ->method('searchBatteriesWithIds')
            ->willReturn($this->batterySearchResponseMock);

        $this
            ->ltiMessagePayloadMock
            ->expects(self::once())
            ->method('getClaim')
            ->with('ltiClaims')
            ->willReturn(['iss' => 'iss', 'aud' => 'aud']);

        $this->batterySearchResponseMock
            ->expects(self::once())
            ->method('getTotalResults')
            ->willReturn(2);

        return new SubmitDeepLinksAction(
            $registrationRepositoryMock,
            $this->validatorMock,
            $this->dynamicQueryApiGatewayMock,
            $this->deepLinkingLaunchResponseBuilderMock,
            $this->resourceCollectionBuilderMock,
            $this->deepLinkingReturnAutoSubmitFormDummy,
        );
    }

    private function expectGetValidatedRequestParameter(...$parameters): void
    {
        $this
            ->validatorMock
            ->expects(self::exactly(count($parameters)))
            ->method('getValidatedRequestParameter')
            ->willReturnOnConsecutiveCalls(...$parameters);
    }

    private function expectSearchDeliveriesWithIds(): void
    {
        $this->deliverySearchResponseMock
            ->expects(self::once())
            ->method('getTotalResults')
            ->willReturn(2);

        $this
            ->dynamicQueryApiGatewayMock
            ->expects(self::once())
            ->method('searchDeliveriesWithIds')
            ->willReturn($this->deliverySearchResponseMock);
    }

    private function expectBatterySearchResponse(): void
    {
        $this->batterySearchResponseMock
            ->expects(self::once())
            ->method('getData')
            ->willReturn(
                [
                    new Battery('bat1', 'bat1', 'bat1', 'mode', 'status', 'audience', self::DELIVERIES),
                    new Battery('bat2', 'bat2', 'bat2', 'mode', 'status', 'audience', self::DELIVERIES),
                ],
            );
    }

    private function expectDeliverySearchResponse(): void
    {
        $this->deliverySearchResponseMock
            ->expects(self::once())
            ->method('getData')
            ->willReturn(
                [
                    new Delivery('del1', [], 'audience', 'foobar', [], new DateTime()),
                    new Delivery('del2', [], 'audience', 'foobar', [], new DateTime()),
                ],
            );
    }

    private function expectGetDeepLinkingSettings(): void
    {
        $this
            ->ltiMessagePayloadMock
            ->expects(self::once())
            ->method('getDeepLinkingSettings')
            ->willReturn($this->createMock(DeepLinkingSettingsClaim::class));
    }

    private function expectBuildDeepLinkingLaunchResponse(string $method, string $response): void
    {
        $ltiResponseMock = $this->createMock(LtiMessageInterface::class);
        $ltiResponseMock
            ->expects(self::once())
            ->method($method)
            ->willReturn($response);
        $this
            ->deepLinkingLaunchResponseBuilderMock
            ->expects(self::once())
            ->method('buildDeepLinkingLaunchResponse')
            ->willReturn($ltiResponseMock);
    }
}
