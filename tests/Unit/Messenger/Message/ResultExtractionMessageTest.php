<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message;

use App\Messenger\Message\ResultExtractionMessage;
use PHPUnit\Framework\TestCase;

class ResultExtractionMessageTest extends TestCase
{
    /** @var ResultExtractionMessage */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new ResultExtractionMessage('id', 'deliveryExecutionId');
    }

    public function testItCanGetTheId(): void
    {
        $this->assertEquals('id', $this->subject->getId());
    }

    public function testItCanGetTheDeliverExecutionId(): void
    {
        $this->assertEquals('deliveryExecutionId', $this->subject->getDeliveryExecutionId());
    }
}
