<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionKeyInfo;
use App\Tests\Helpers\ContainerAwareTestingHelper;
use DateTimeImmutable;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use App\Domain\Delivery\Model\Delivery;

trait OAuth2SecurityTestingTrait
{
    protected function setUp(): void
    {
        $this->setUpTestOAuth2AccessTokenFactory();
    }

    protected function setUpTestOAuth2AccessTokenFactory(): void
    {
        ContainerAwareTestingHelper::checkKernelTestCase(static::class);
    }

    public function createOAuth2AccessToken(string $deliveryExecutionId = '1', array $ltiClaims = []): string
    {
        $info = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($deliveryExecutionId)
            ?? new DeliveryExecutionKeyInfo(null, 'userId', 'deliveryId', '', $deliveryExecutionId);
        $ltiClaims[LtiMessagePayloadInterface::CLAIM_LTI_TARGET_LINK_URI] = sprintf(
            "https://deliver.ngs.test/api/v1/launch-lti-1p3/%s",
            $info->getDeliveryId(),
        );
        if ($info->getUserId() !== null) {
            $ltiClaims['sub'] = $info->getUserId();
        }

        $key = InMemory::base64Encoded(
            'hiG8DlOKvtih6AxlZn5XKImZ06yu8I3mkOzaJrEuW8yAv8Jnkw330uMt8AEqQ5LB',
        );

        $token = (new JwtFacade())->issue(
            new Sha256(),
            $key,
            static function (
                Builder $builder,
                DateTimeImmutable $issuedAt,
            ) use (
                $info,
                $ltiClaims
            ): Builder {
                $builder
                    ->issuedBy('https://deliver.ngs.test')
                    ->permittedFor('tests')
                    ->expiresAt($issuedAt->modify('+1 minute'))
                    ->withClaim('tenant_id', $info->getTenantId())
                    ->withClaim('ltiClaims', $ltiClaims);

                foreach ($ltiClaims as $key => $value) {
                    if ($key === 'sub') {
                        $builder->relatedTo($value);
                    } else {
                        $builder->withClaim($key, $value);
                    }
                }

                return $builder;
            },
        );

        return $token->toString();
    }

    public function createOAuth2AccessTokenByDelivery(Delivery $delivery, ?string $userId = null): string
    {
        $ltiClaims[LtiMessagePayloadInterface::CLAIM_LTI_TARGET_LINK_URI] = sprintf(
            "https://deliver.ngs.test/api/v1/launch-lti-1p3/%s",
            $delivery->getId(),
        );

        if ($userId !== null) {
            $ltiClaims['sub'] = $userId;
        }

        $key = InMemory::base64Encoded(
            'hiG8DlOKvtih6AxlZn5XKImZ06yu8I3mkOzaJrEuW8yAv8Jnkw330uMt8AEqQ5LB',
        );

        $token = (new JwtFacade())->issue(
            new Sha256(),
            $key,
            static function (
                Builder $builder,
                DateTimeImmutable $issuedAt,
            ) use (
                $delivery,
                $ltiClaims
            ): Builder {
                $builder
                    ->issuedBy('https://deliver.ngs.test')
                    ->permittedFor('tests')
                    ->expiresAt($issuedAt->modify('+1 minute'))
                    ->withClaim('tenant_id', $delivery->getTenantId())
                    ->withClaim('ltiClaims', $ltiClaims);

                foreach ($ltiClaims as $key => $value) {
                    if ($key === 'sub') {
                        $builder->relatedTo($value);
                    } else {
                        $builder->withClaim($key, $value);
                    }
                }

                return $builder;
            },
        );

        return $token->toString();
    }
}
