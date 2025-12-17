<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Security\EventSubscriber;

use App\Request\Extractor\RequestContextExtractor;
use App\Request\Extractor\TokenContextExtractor;
use App\Security\Contract\DeliveryExecutionSessionController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(KernelEvents::CONTROLLER, 'onKernelController')]
readonly class DeliveryExecutionSessionSubscriber
{
    public function __construct(
        private TokenContextExtractor $tokenContextExtractor,
        private RequestContextExtractor $requestContextExtractor,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $id = $event->getRequest()->attributes->get('id');
        if (null !== $id) {
            $this->requestContextExtractor->setId($id);
        }

        $controller = $event->getController();

        // when a controller class defines multiple action methods, the controller
        // is returned as [$controllerInstance, 'methodName']
        if (is_array($controller)) {
            $controller = $controller[0];
        }

        if ($controller instanceof DeliveryExecutionSessionController) {
            $this->validateAccessToken($event->getRequest());
        }
    }

    private function validateAccessToken(Request $request): void
    {
        if (!$this->tokenContextExtractor->supports($request)) {
            throw new UnauthorizedHttpException('Bearer', 'Access token is missing.');
        }

        $contextDiff = $this->requestContextExtractor->extract()->fits($this->tokenContextExtractor->extract());
        if ($contextDiff) {
            throw new AccessDeniedHttpException(json_encode($contextDiff));
        }
    }
}
