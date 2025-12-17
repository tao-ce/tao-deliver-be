<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Publication;

use App\Domain\Publication\Model\Publication;
use App\Responder\SerializerResponder;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetPublicationAction
{
    /** @var SerializerResponder */
    private $responder;

    public function __construct(SerializerResponder $responder)
    {
        $this->responder = $responder;
    }

    public function __invoke(Publication $publication): JsonResponse
    {
        return $this->responder->createJsonResponse(['data' => $publication]);
    }
}
