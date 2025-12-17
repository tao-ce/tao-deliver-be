<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\User\Model;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    /** @var string */
    private $id;

    /** @var string */
    private $tenantId;

    /** @var array */
    private $roles;

    /** @var string */
    private $username;

    public function __construct(string $id, string $tenantId, array $roles, ?string $username = null)
    {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->roles = $roles;
        $this->username = $username;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    /** @inheritDoc */
    public function getPassword(): string
    {
        return '';
    }

    /** @inheritDoc */
    public function getSalt(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /** @inheritDoc */
    public function eraseCredentials(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
