<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Serializer\Normalizer\DeliveryNormalizer;
use App\Service\Delivery\GenerateDeliveryLtiLaunchUrlService;
use App\Tests\Traits\DomainTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeliveryNormalizerTest extends TestCase
{
    use DomainTestingTrait;

    private DeliveryNormalizer $subject;
    private GenerateDeliveryLtiLaunchUrlService|MockObject $generateDeliveryLtiLaunchUrlServiceMock;

    protected function setUp(): void
    {
        $this->generateDeliveryLtiLaunchUrlServiceMock = $this->createMock(GenerateDeliveryLtiLaunchUrlService::class);

        $this->subject = new DeliveryNormalizer($this->generateDeliveryLtiLaunchUrlServiceMock);
    }

    public function testNormalizationSupport(): void
    {
        $this->assertTrue($this->subject->supportsNormalization($this->createTestDelivery()));
        $this->assertFalse($this->subject->supportsNormalization('invalid'));
    }

    public function testNormalizationSuccess(): void
    {
        $delivery = $this->createTestDelivery();

        $this->generateDeliveryLtiLaunchUrlServiceMock
            ->expects($this->once())
            ->method('generate')
            ->with($delivery)
            ->willReturn('generatedUrl');

        $normalization = $this->subject->normalize($delivery);

        $this->assertEquals(
            [
                'id' => $delivery->getId(),
                'tenantId' => $delivery->getTenantId(),
                'draftId' => $delivery->getDraftId(),
                'createdAt' => $delivery->getCreatedAt()->getTimestamp(),
                'configuration' => $delivery->getConfiguration(),
                'compactTestFilePath' => $delivery->getQtiCompactTestFilePath(),
                'isDisabled' => $delivery->getIsDisabled(),
                'mainLocale' => $delivery->getMainLocale(),
                'supportedLocales' => $delivery->getSupportedLocales(),
                'translations' => $delivery->getTranslations(),
                'launchUrl' => 'generatedUrl',
                'version' => '1',
            ],
            $normalization,
        );
    }
}
