<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Service\Lti;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use Lcobucci\JWT\UnencryptedToken;

interface LtiTokenResolverInterface
{
    public const LTI_ROLE_INSTRUCTOR = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
    public const LTI_ROLE_LEARNER = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';

    public function resolve(DeliveryExecution $deliveryExecution): UnencryptedToken;
    public function resolveFromRequest(): ?UnencryptedToken;
    public function hasOneOfRoles(array $roles): bool;
}
