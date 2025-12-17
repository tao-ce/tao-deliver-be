<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Exception;

use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LtiLaunchException extends LtiException implements HttpExceptionInterface
{
    /** @var LtiMessagePayloadInterface */
    private $ltiMessage;

    public function setLtiMessage(LtiMessagePayloadInterface $ltiMessagePayload): self
    {
        $this->ltiMessage = $ltiMessagePayload;

        return $this;
    }

    public function getLtiMessage(): LtiMessagePayloadInterface
    {
        return $this->ltiMessage;
    }

    public function getStatusCode(): int
    {
        return $this->getPrevious() instanceof HttpExceptionInterface
            ? $this->getPrevious()->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getHeaders(): array
    {
        return $this->getPrevious() instanceof HttpExceptionInterface
            ? $this->getPrevious()->getHeaders()
            : [];
    }
}
