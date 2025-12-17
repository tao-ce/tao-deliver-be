<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Validator\Exception;

use App\Validator\Exception\RequestValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class RequestValidationExceptionTest extends TestCase
{
    /** @var RequestValidationException */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new RequestValidationException('message');
    }

    public function testStatusCode(): void
    {
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->subject->getStatusCode());
    }

    public function testHeaders(): void
    {
        $this->assertEquals([], $this->subject->getHeaders());
    }
}
