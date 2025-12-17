<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\MessageBus;

use App\Messenger\MessageBus\PostProcessedMessageBus;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\Tests\Traits\DataStoreTestingTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use stdClass;
use Symfony\Component\Messenger\MessageBusInterface;

class PostProcessedMessageBusTest extends TestCase
{
    use DataStoreTestingTrait;

    private MessageBusInterface $messageBusMock;
    private PostProcessedMessageBusInterface $subject;

    protected function setUp(): void
    {
        $this->messageBusMock = $this->getMessageBusMock();
        $this->subject = new PostProcessedMessageBus($this->messageBusMock);
    }

    public function testMessageNotDispatchImmediately(): void
    {
        $this->subject->dispatch(new stdClass());

        $this->assertNotEmpty($this->subject->getDispatchWaitingList());
    }

    public function testMessageBusCouldBeFree(): void
    {
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new stdClass()));

        $this->subject->dispatch(new stdClass());
        $this->subject->free();

        $this->assertEmpty($this->subject->getDispatchWaitingList());
    }
}
