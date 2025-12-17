<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\EventSubscriber\ResponseSecurityHeaderSubscriber;
use OAT\Library\Lti1p3Core\Message\LtiMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ResponseSecurityHeaderSubscriberTest extends KernelTestCase
{
    private ResponseSecurityHeaderSubscriber $subject;

    protected function setUp(): void
    {
        static::bootKernel();

        parent::setUp();

        $this->subject = new ResponseSecurityHeaderSubscriber();
    }

    public function testAddsSecurityHeadersToResponse(): void
    {
        $event = new ResponseEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $this->subject->onKernelResponse($event);

        $this->assertResponseContainsSecurityHeaders($event->getResponse());
    }

    public function testExistingHeadersAreKept(): void
    {
        $event = new ResponseEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response('', Response::HTTP_OK, ['Content-Type' => 'application/json']),
        );

        $this->subject->onKernelResponse($event);

        $this->assertResponseContainsSecurityHeaders($event->getResponse());
        $this->assertTrue($event->getResponse()->headers->has('Content-Type'));
    }

    public function testDoesNotAddSecurityHeadersOnSubRequests(): void
    {
        $event = new ResponseEvent(
            static::$kernel,
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
            new Response(),
        );

        $this->subject->onKernelResponse($event);

        $this->assertResponseDoesNotContainSecurityHeaders($event->getResponse());
    }

    public function testAddsUnsafeInlineValueForContentSecurityPolicyHeaderOnLtiLaunchRedirects(): void
    {
        $event = new ResponseEvent(
            static::$kernel,
            Request::create('/api/v1/auth/launch-lti-1p3'),
            HttpKernelInterface::MAIN_REQUEST,
            new RedirectResponse('/api/v1/auth/launch-lti-1p3'),
        );

        $this->subject->onKernelResponse($event);

        $response = $event->getResponse();

        $this->assertEquals(
            'default-src \'self\' \'unsafe-inline\'; object-src \'none\'; child-src \'self\'; upgrade-insecure-requests; block-all-mixed-content',
            $response->headers->get('Content-Security-Policy'),
        );
        $this->assertResponseContainsStrictTransportSecurityHeader($response);
        $this->assertResponseContainsContentTypeOptionsHeader($response);
    }

    public function testAddsUnsafeInlineValueForContentSecurityPolicyHeaderOnLtiLaunchAutoSubmitForms(): void
    {
        $event = new ResponseEvent(
            static::$kernel,
            Request::create('/api/v1/auth/launch-lti-1p3'),
            HttpKernelInterface::MAIN_REQUEST,
            new Response((new LtiMessage('/api/v1/auth/launch-lti-1p3'))->toHtmlRedirectForm()),
        );

        $this->subject->onKernelResponse($event);

        $response = $event->getResponse();

        $this->assertEquals(
            'default-src \'self\' \'unsafe-inline\'; object-src \'none\'; child-src \'self\'; upgrade-insecure-requests; block-all-mixed-content',
            $response->headers->get('Content-Security-Policy'),
        );
        $this->assertResponseContainsStrictTransportSecurityHeader($response);
        $this->assertResponseContainsContentTypeOptionsHeader($response);
    }

    private function assertResponseContainsStrictTransportSecurityHeader(Response $response): void
    {
        $this->assertEquals('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    private function assertResponseContainsContentTypeOptionsHeader(Response $response): void
    {
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    private function assertResponseContainsSecurityHeaders(Response $response): void
    {
        $this->assertResponseContainsStrictTransportSecurityHeader($response);
        $this->assertResponseContainsContentTypeOptionsHeader($response);

        $this->assertEquals(
            'default-src \'self\'; object-src \'none\'; child-src \'self\'; frame-ancestors \'none\'; upgrade-insecure-requests; block-all-mixed-content',
            $response->headers->get('Content-Security-Policy'),
        );
    }

    private function assertResponseDoesNotContainSecurityHeaders(Response $response): void
    {
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
        $this->assertFalse($response->headers->has('X-Content-Type-Options'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }
}
