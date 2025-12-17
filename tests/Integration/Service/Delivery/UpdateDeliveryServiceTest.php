<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Service\Delivery;

use App\Service\Delivery\UpdateDeliveryService;
use App\Tests\Traits\DomainTestingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UpdateDeliveryServiceTest extends KernelTestCase
{
    use DomainTestingTrait;

    /** @var UpdateDeliveryService */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();

        $this->subject = static::getContainer()->get(UpdateDeliveryService::class);
    }

    public function testItCanUpdateDelivery(): void
    {
        $initialConfiguration = ['property' => 'value'];
        $delivery = $this->createTestDelivery(configuration: $initialConfiguration);

        $this->subject->update($delivery, ['property2' => 'value2']);

        $this->assertEquals(['property2' => 'value2'], $delivery->getConfiguration());
    }
}
