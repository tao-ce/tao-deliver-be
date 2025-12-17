<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Security\User;

use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use JsonException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResult;
use OAT\Library\Lti1p3Core\Security\User\Result\UserAuthenticationResultInterface;
use OAT\Library\Lti1p3Core\Security\User\UserAuthenticatorInterface;
use OAT\Library\Lti1p3Core\User\UserIdentityFactoryInterface;

class UserAuthenticator implements UserAuthenticatorInterface
{
    public function __construct(
        private UserIdentityFactoryInterface $factory,
        private RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function authenticate(RegistrationInterface $registration, string $loginHint): UserAuthenticationResultInterface
    {
        $hint = json_decode($loginHint, true, 512, JSON_THROW_ON_ERROR);

        if (empty($hint['sub']) && empty($hint['user_id'])) {
            return new UserAuthenticationResult(false);
        }

        if (empty($hint['delivery_execution_id'])) {
            return $this->createUserAuthenticationResult($hint);
        }

        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($hint['delivery_execution_id']);
        } catch (DocumentNotFoundException $e) {
            return new UserAuthenticationResult(false);
        }

        $params = $deliveryExecution->getLtiLaunchParameters();

        if ($params['user_id'] === $hint['sub']) {
            return $this->createUserAuthenticationResult($hint);
        }

        return new UserAuthenticationResult(false);
    }

    /**
     * @param mixed $hint
     * @return UserAuthenticationResult
     */
    private function createUserAuthenticationResult(array $hint): UserAuthenticationResult
    {
        return new UserAuthenticationResult(
            true,
            $this->factory->create(
                $hint['sub'] ?? $hint['user_id'],
                $hint['name'] ?? null,
                $hint['email'] ?? null,
                $hint['given_name'] ?? null,
                $hint['family_name'] ?? null,
                $hint['middle_name'] ?? null,
                $hint['locale'] ?? null,
                $hint['picture'] ?? null,
            ),
        );
    }
}
