<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseSecurityHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->add(
            [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );

        $this->addContentSecurityPolicyHeader($event);
    }

    /**
     * To avoid browser security issues with LTI authentication, we need to add a more permissive value in the directive
     * "default-src" for the header "Content-Security-Policy".
     */
    private function addContentSecurityPolicyHeader(ResponseEvent $event): void
    {
        if (
            $event->getResponse() instanceof RedirectResponse
            || str_starts_with($event->getResponse()->getContent() ?: '', '<form id="launch_')
        ) {
            $extraPermissiveValue = ' \'unsafe-inline\'';
            $frameAncestors = '';
        } else {
            $extraPermissiveValue = '';
            $frameAncestors = ' frame-ancestors \'none\';';
        }

        $event->getResponse()->headers->add(
            [
                'Content-Security-Policy' => "default-src 'self'$extraPermissiveValue; object-src 'none'; child-src 'self';$frameAncestors upgrade-insecure-requests; block-all-mixed-content",
            ],
        );
    }
}
