<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Serializer\Normalizer;

use App\Serializer\Normalizer\PublicationNormalizer;
use App\Tests\Traits\DomainTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicationNormalizerTest extends TestCase
{
    use DomainTestingTrait;

    /** @var PublicationNormalizer */
    private $subject;

    /** @var UrlGeneratorInterface|MockObject */
    private $urlGeneratorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->urlGeneratorMock = $this->createMock(UrlGeneratorInterface::class);

        $this->subject = new PublicationNormalizer($this->urlGeneratorMock);
    }

    public function testNormalizationSupport(): void
    {
        $this->assertTrue($this->subject->supportsNormalization($this->createTestPublication()));
        $this->assertFalse($this->subject->supportsNormalization('invalid'));
    }

    public function testNormalizationSuccess(): void
    {
        $publication = $this->createTestPublication();

        $this->urlGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->with('api_v1_get_publication', ['id' => $publication->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('generatedUrl');

        $normalization = $this->subject->normalize($publication);

        $this->assertEquals(
            [
                'id' => $publication->getId(),
                'status' => $publication->getStatus(),
                'url' => 'generatedUrl',
                'tenantId' => $publication->getTenantId(),
                'deliveryId' => $publication->getDeliveryId(),
                'reports' => $publication->getReports(),
                'locale' => $publication->getLocale(),
            ],
            $normalization,
        );
    }
}
