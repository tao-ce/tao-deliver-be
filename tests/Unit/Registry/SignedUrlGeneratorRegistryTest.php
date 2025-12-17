<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Registry;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Generator\Asset\CloudStorageSignedUrlGenerator;
use App\Generator\Asset\LocalSignedUrlGenerator;
use App\Generator\Asset\SignedUrlGeneratorInterface;
use App\Registry\SignedUrlGeneratorRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SignedUrlGeneratorRegistryTest extends TestCase
{
    /** @var SignedUrlGeneratorRegistry */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new SignedUrlGeneratorRegistry($this->createGenerators(
            LocalSignedUrlGenerator::NAME,
            CloudStorageSignedUrlGenerator::NAME,
        ));
    }

    public function testItGetsGenerator(): void
    {
        self::assertEquals(
            LocalSignedUrlGenerator::NAME,
            $this->subject->getGenerator(LocalSignedUrlGenerator::NAME)->getName(),
        );

        self::assertEquals(
            CloudStorageSignedUrlGenerator::NAME,
            $this->subject->getGenerator(CloudStorageSignedUrlGenerator::NAME)->getName(),
        );
    }

    public function testItGetsLocalGeneratorWhenNotFound(): void
    {
        self::assertEquals(
            LocalSignedUrlGenerator::NAME,
            $this->subject->getGenerator(CloudCdnSignedUrlGenerator::NAME)->getName(),
        );
    }

    public function testItThrowsExceptionWhenSenderNotFound(): void
    {
        $subject = new SignedUrlGeneratorRegistry($this->createGenerators(CloudStorageSignedUrlGenerator::NAME));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No url generator associated to the name given unknown');

        $subject->getGenerator('unknown');
    }

    private function createGenerators(string ...$name): array
    {
        return array_map(
            static function ($name) {
                return new class ($name) implements SignedUrlGeneratorInterface {
                    private $name;

                    public function __construct($name)
                    {
                        $this->name = $name;
                    }

                    public function generateDownloadUrl(string $path, ?string $url = null, array $queryParameters = [], ?int $ttl = null): string
                    {
                        return $this->name . '/download/url';
                    }

                    public function generateUploadUrl(?string $path = null): string
                    {
                        return $this->name . '/upload/url';
                    }

                    public function getName(): string
                    {
                        return $this->name;
                    }

                    public function getFeServiceId(): string
                    {
                        return $this->name . 'Id';
                    }

                    public function getUploadMethod(): ?string
                    {
                        return 'method';
                    }
                };
            },
            func_get_args(),
        );
    }
}
