<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Generator;

use App\Generator\UrlGenerator;
use App\Service\ApplicationInfoService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlGeneratorTest extends TestCase
{
    private UrlGenerator $subject;
    private ApplicationInfoService|MockObject $applicationInfoServiceMock;
    private UrlGeneratorInterface|MockObject $urlGeneratorMock;

    protected function setUp(): void
    {
        $this->applicationInfoServiceMock = $this->createMock(ApplicationInfoService::class);
        $this->urlGeneratorMock = $this->createMock(UrlGeneratorInterface::class);
        $this->subject = new UrlGenerator($this->applicationInfoServiceMock, $this->urlGeneratorMock);
    }

    public function testGenerate(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->once())
            ->method('getBackendUrl')
            ->willReturn('https://backend.url');

        $this->urlGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->with('name', ['parameter1' => 'value1'])
            ->willReturn('/api/v1/path');

        $this->assertSame(
            'https://backend.url/api/v1/path',
            $this->subject->generate('name', ['parameter1' => 'value1']),
        );
    }

    public function testGenerateWithPathPrefix(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->once())
            ->method('getBackendUrl')
            ->willReturn('https://backend.url/app');

        $this->urlGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->with('name', ['parameter1' => 'value1'])
            ->willReturn('/api/v1/path');

        $this->assertSame(
            'https://backend.url/app/api/v1/path',
            $this->subject->generate('name', ['parameter1' => 'value1']),
        );
    }

    public function testGenerateWithNetworkPath(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->once())
            ->method('getBackendUrl')
            ->willReturn('https://backend.url');

        $this->urlGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->with('name', ['parameter1' => 'value1'])
            ->willReturn('/api/v1/path');

        $this->assertSame(
            '//backend.url/api/v1/path',
            $this->subject->generate(
                'name',
                ['parameter1' => 'value1'],
                UrlGeneratorInterface::NETWORK_PATH,
            ),
        );
    }

    public function testGenerateWithNetworkPathAndPathPrefix(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->once())
            ->method('getBackendUrl')
            ->willReturn('https://backend.url/app');

        $this->urlGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->with('name', ['parameter1' => 'value1'])
            ->willReturn('/api/v1/path');

        $this->assertSame(
            '//backend.url/app/api/v1/path',
            $this->subject->generate(
                'name',
                ['parameter1' => 'value1'],
                UrlGeneratorInterface::NETWORK_PATH,
            ),
        );
    }

    /**
     * @dataProvider unsupportedReferenceTypeProvider
     */
    public function testGenerateWithUnsupportedReferenceType(int $referenceType): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->never())
            ->method('getBackendUrl');

        $this->urlGeneratorMock
            ->expects($this->never())
            ->method('generate');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported reference type');

        $this->assertSame(
            '//backend.url/app/api/v1/path',
            $this->subject->generate(
                'name',
                ['parameter1' => 'value1'],
                $referenceType,
            ),
        );
    }

    public function unsupportedReferenceTypeProvider(): array
    {
        return [
            [UrlGeneratorInterface::ABSOLUTE_PATH],
            [UrlGeneratorInterface::RELATIVE_PATH],
        ];
    }
}
