<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace Tests\Unit\Domain\DeliveryExecution\Model;

use App\Domain\DeliveryExecution\Model\Invalidation;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class InvalidationTest extends TestCase
{
    public function testCreateinvalidation(): void
    {
        $userLogin = 'test_user';
        $timestamp = Carbon::now();

        $metadata = new Invalidation($userLogin, $timestamp, true);

        $this->assertEquals($userLogin, $metadata->getInvalidatedBy());
        $this->assertEquals($timestamp, $metadata->getInvalidatedAt());
        $this->assertTrue($metadata->isResultInvalidated());
    }

    public function testCreateFromStaticMethod(): void
    {
        $userLogin = 'test_user';

        $metadata = Invalidation::create($userLogin);

        $this->assertEquals($userLogin, $metadata->getInvalidatedBy());
        $this->assertInstanceOf(\DateTimeInterface::class, $metadata->getInvalidatedAt());
        $this->assertTrue($metadata->isResultInvalidated());
    }

    public function testToArray(): void
    {
        $userLogin = 'test_user';
        $timestamp = Carbon::createFromTimestamp(1234567890);

        $metadata = new Invalidation($userLogin, $timestamp, true);
        $array = $metadata->toArray();

        $expected = [
            'invalidatedBy' => $userLogin,
            'invalidatedAt' => 1234567890,
            'isResultInvalidated' => true,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testFromArray(): void
    {
        $data = [
            'invalidatedBy' => 'test_user',
            'invalidatedAt' => 1234567890,
            'isResultInvalidated' => true,
        ];

        $metadata = Invalidation::fromArray($data);

        $this->assertEquals('test_user', $metadata->getInvalidatedBy());
        $this->assertEquals(1234567890, $metadata->getInvalidatedAt()->getTimestamp());
        $this->assertTrue($metadata->isResultInvalidated());
    }

    public function testFromArrayWithDefaultValue(): void
    {
        $data = [
            'invalidatedBy' => 'test_user',
            'invalidatedAt' => 1234567890,
        ];

        $metadata = Invalidation::fromArray($data);

        $this->assertEquals('test_user', $metadata->getInvalidatedBy());
        $this->assertEquals(1234567890, $metadata->getInvalidatedAt()->getTimestamp());
        $this->assertTrue($metadata->isResultInvalidated()); // Should default to true
    }
}
