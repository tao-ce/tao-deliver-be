<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\Key;
use OAT\Library\Lti1p3Core\Security\Key\KeyChain;
use OAT\Library\Lti1p3Core\Tool\Tool;

trait RegistrationRepositoryTestingTrait
{
    private function mockRegistrationRepository(): void
    {
        $privateKey = file_get_contents(__DIR__ . '/../Resources/config/keys/private.key');
        $publicKey = file_get_contents(__DIR__ . '/../Resources/config/keys/public.key');

        $registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $registrationRepositoryMock
            ->method('findByPlatformIssuer')
            ->willReturn(new Registration(
                'identifier',
                'clientId',
                new Platform('platformIdentifier', 'platformName', 'platformAudience'),
                new Tool('toolIdentifier', 'toolName', 'toolAudience', 'toolOidcInitiationUrl'),
                ['deploymentId'],
                new KeyChain('platformKeyChainIdentifier', 'platformKeyChainKeySetName', new Key($publicKey), new Key($privateKey)),
                new KeyChain('toolKeyChainIdentifier', 'toolKeyChainKeySetName', new Key($publicKey), new Key($privateKey)),
            ));

        static::getContainer()->set(RegistrationRepositoryInterface::class, $registrationRepositoryMock);
    }
}
