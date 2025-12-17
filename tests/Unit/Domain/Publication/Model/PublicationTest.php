<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Publication\Model;

use App\Domain\Publication\Model\Publication;
use PHPUnit\Framework\TestCase;

class PublicationTest extends TestCase
{
    /** @var Publication */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Publication(
            'id',
            'tenantId',
            'package/Path',
            'http://package/location',
            ['package' => 'configuration'],
            [['type' => 'success', 'message' => 'success']],
            'status',
            'deliveryId',
        );
    }

    public function testConstructorDefaultValues(): void
    {
        $publication = new Publication(
            'id',
            'tenantId',
            'package/Path',
        );

        $this->assertEmpty($publication->getPackageConfiguration());
        $this->assertEmpty($publication->getReports());
        $this->assertEquals(Publication::STATUS_CREATED, $publication->getStatus());
        $this->assertNull($publication->getDeliveryId());
    }

    public function testItCanRetrieveTheId(): void
    {
        $this->assertSame('id', $this->subject->getId());
    }

    public function testItCanRetrieveTheStatus(): void
    {
        $this->assertSame('status', $this->subject->getStatus());
    }

    public function testItCanRetrieveTheTenantId(): void
    {
        $this->assertSame('tenantId', $this->subject->getTenantId());
    }

    public function testItCanRetrieveTheDeliveryId(): void
    {
        $this->assertSame('deliveryId', $this->subject->getDeliveryId());
    }

    public function testItCanRetrieveThePackagePath(): void
    {
        $this->assertSame('package/Path', $this->subject->getPackagePath());
    }

    public function testItCanRetrieveThePackageConfiguration(): void
    {
        $this->assertSame(['package' => 'configuration'], $this->subject->getPackageConfiguration());
    }

    public function testItCanRetrieveTheReports(): void
    {
        $this->assertSame([['type' => 'success', 'message' => 'success']], $this->subject->getReports());
    }

    public function testItCanChangeTheDeliveryId(): void
    {
        $newDeliveryId = 'newDeliveryId';

        $this->assertSame($newDeliveryId, $this->subject->setDeliveryId($newDeliveryId)->getDeliveryId());
    }

    public function testItCanChangeTheReports(): void
    {
        $reports = [
            ['type' => 'error', 'message' => 'error'],
        ];

        $this->assertSame($reports, $this->subject->setReports($reports)->getReports());
    }

    public function testSetStatus(): void
    {
        $newStatus = 'newStatus';

        $this->assertSame($newStatus, $this->subject->setStatus($newStatus)->getStatus());
    }
}
