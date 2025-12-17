<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ItemEnricher;

use App\TestRunner\ItemEnricher\{Contract\ItemStateEnricherInterface, ExtendedTextGenerateSignedDownloadUrlEnricher};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExtendedTextGenerateSignedDownloadUrlEnricherTest extends KernelTestCase
{
    private ItemStateEnricherInterface $extendedTextEnricher;

    public function setUp(): void
    {
        static::bootKernel();
        $this->extendedTextEnricher = static::getContainer()->get(ExtendedTextGenerateSignedDownloadUrlEnricher::class);
    }

    public function testUnknownStateSkipped(): void
    {
        $state = 'not an object';
        $this->assertSame($state, $this->extendedTextEnricher->enrich($state));
    }

    public function testImageDetectedAndParsed(): void
    {
        $state = $this->getState();
        $response = $this->extendedTextEnricher->enrich($state);

        $this->assertNotEquals($response['response'], $state['response']);
        $this->assertNotEquals($response['history'], $state['history']);
    }

    public function testTestNotAddAdditionalDataIfImageNotProvided(): void
    {
        $updatedState = $this->extendedTextEnricher->enrich($this->getState(false));
        $this->assertEquals($updatedState, $this->getState(false));
    }

    private function getState(bool $withImg = true): array
    {
        return [
            'validity' => true,
            'response' => [
                'base' => [
                    'string' => !$withImg
                        ? '<p>hello</p ><p > world</p >'
                        : '<p>hello</p><figure class="image"><img src="https://..." data-img-id="xyz"></figure><p>world</p>',
                ],
            ],
            'history' => [
                'response' => [
                    'base' => [
                        'string' => !$withImg
                            ? '<p>hello history</p ><p > world2</p >'
                            : '<p>hello history</p><figure class="image"><img src="https://..." data-img-id="xyz"></figure><p>world history</p>',
                    ],
                ],
            ],
        ];
    }
}
