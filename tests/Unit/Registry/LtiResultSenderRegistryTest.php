<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Registry;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\Sender\LtiResultSenderInterface;
use App\Messenger\Message\ResultExtractionMessage;
use App\Registry\LtiResultSenderRegistry;
use PHPUnit\Framework\TestCase;

class LtiResultSenderRegistryTest extends TestCase
{
    /** @var LtiResultSenderRegistry */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new LtiResultSenderRegistry($this->createSenders());
    }

    public function testItGetSender(): void
    {
        $this->assertEquals($this->createSenders(), $this->subject->getSenders());
    }

    private function createSenders(): array
    {
        return [
            new class implements LtiResultSenderInterface {
                public function getLtiVersion(): string
                {
                    return 'firstSender';
                }

                public function send(DeliveryExecution $deliveryExecution, array $resultData, ResultExtractionMessage $message): void
                {
                }
            },
            new class implements LtiResultSenderInterface {
                public function getLtiVersion(): string
                {
                    return 'secondSender';
                }

                public function send(DeliveryExecution $deliveryExecution, array $resultData, ResultExtractionMessage $message): void
                {
                }
            },
        ];
    }
}
