<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use OAT\Library\EnvironmentManagementLtiClient\Client\LtiAgsClientInterface;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;

trait AgsTestingTrait
{
    private function mockPublishScore(?InvocationOrder $invocationRule = null): void
    {
        $agsClientMock = $this->createMock(LtiAgsClientInterface::class);
        $agsClientMock
            ->expects($invocationRule ?? $this->once())
            ->method('publishScore');

        static::getContainer()->set(LtiAgsClientInterface::class, $agsClientMock);
    }
}
