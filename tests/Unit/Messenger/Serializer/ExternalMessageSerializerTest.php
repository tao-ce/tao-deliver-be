<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Serializer;

use App\Messenger\Serializer\ExternalMessageSerializer;
use App\Tests\Stubs\NormalizedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExternalMessageSerializerTest extends KernelTestCase
{
    private ExternalMessageSerializer $sub;

    /**
     * @before
     */
    public function init(): void
    {
        $this->sub = new ExternalMessageSerializer(
            NormalizedMessage::class,
            $this->getContainer()->get('serializer.normalizer.object'),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testDenormalization(): void
    {
        $data = [
            'value' => 'test_value',
        ];

        $this->assertSame(
            $data,
            $this->sub->encode($this->sub->decode($data)),
        );
    }

    public function testNormalization(): void
    {
        $value = 'test_value';

        $envelope = $this->sub->decode(compact('value'));
        $this->assertInstanceOf(NormalizedMessage::class, $envelope->getMessage());
        $this->assertSame(
            $value,
            $envelope->getMessage()->getValue(),
        );
    }
}
