<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\User\Model\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class UserTest extends TestCase
{
    /** @var UserInterface */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        $this->subject = new User(
            'userId',
            'tenantId',
            ['ROLE_LEARNER'],
            'userName',
        );
    }

    public function testItImplementsDocumentInterface(): void
    {
        $this->assertInstanceOf(UserInterface::class, $this->subject);
    }

    public function testItCanRetrievesTheUserId(): void
    {
        $this->assertEquals('userId', $this->subject->getId());
    }

    public function testItCanRetrievesTheTenantId(): void
    {
        $this->assertEquals('tenantId', $this->subject->getTenantId());
    }

    public function testItRetrievesAnEmptyPassword(): void
    {
        $this->assertEmpty($this->subject->getPassword());
    }

    public function testItCanRetrievesTheRoles(): void
    {
        $this->assertEquals(['ROLE_LEARNER'], $this->subject->getRoles());
    }

    public function testItRetrievesNullAsSalt(): void
    {
        $this->assertNull($this->subject->getSalt());
    }

    public function testItRetrievesTheUsername(): void
    {
        $this->assertEquals('userName', $this->subject->getUsername());
    }

    public function testItCanEraseCredentials(): void
    {
        $this->assertTrue($this->subject->eraseCredentials());
    }
}
