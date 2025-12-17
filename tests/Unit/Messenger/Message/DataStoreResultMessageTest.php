<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message;

use App\Messenger\Message\DataStoreResultMessage;
use App\Messenger\Message\ResultExtractionMessage;
use PHPUnit\Framework\TestCase;

class DataStoreResultMessageTest extends TestCase
{
    /** @var ResultExtractionMessage */
    private $subject;

    /** @var array */
    private $deliveryResultArray = ['DeliveryResultArray'];

    protected function setUp(): void
    {
        $this->subject = new DataStoreResultMessage($this->deliveryResultArray);
    }

    public function testItCanGetTheDeliveryResult(): void
    {
        $this->assertEquals($this->deliveryResultArray, $this->subject->getDeliveryResult());
    }
}
