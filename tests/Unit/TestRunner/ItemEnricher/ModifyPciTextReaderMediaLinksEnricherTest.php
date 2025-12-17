<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ItemEnricher;

use App\TestRunner\ItemEnricher\ModifyPciTextReaderMediaLinksEnricher;
use App\Tests\Traits\DomainTestingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ModifyPciTextReaderMediaLinksEnricherTest extends KernelTestCase
{
    use DomainTestingTrait;

    private ModifyPciTextReaderMediaLinksEnricher $enricher;

    public function setUp(): void
    {
        static::bootKernel();
        $this->enricher = static::getContainer()->get(ModifyPciTextReaderMediaLinksEnricher::class);
    }

    public function testItReplacesMultipleInlinedMediaFiles()
    {
        $propertyWithInlinedMedia = '{"files": ["base64_decoded_a31eafe55ebb25808079fdec50f3cb41.png", "base64_decoded_2da88292cad35c70c0796a69c88e0a77.png"]}';
        $updatedState = $this->enricher->enrich(
            $this->createTestDeliveryExecution(),
            'test',
            $this->getItemData($propertyWithInlinedMedia),
        );
        $this->assertNotEquals($updatedState, $this->getItemData($propertyWithInlinedMedia));
        $this->assertEquals(200, $updatedState['data']['body']['elements'][0]['properties']['height']);
        $this->assertSame(
            '{"files": ["//tao_deliver_be_nginx/api/v1/asset?path=deliveryId%2Ftest%2Fbase64_decoded_a31eafe55ebb25808079fdec50f3cb41.png", "//tao_deliver_be_nginx/api/v1/asset?path=deliveryId%2Ftest%2Fbase64_decoded_2da88292cad35c70c0796a69c88e0a77.png"]}',
            $updatedState['data']['body']['elements'][0]['properties']['content-taomedia:testKey'],
        );
    }

    public function testItemDataIsParsedAndReplaced()
    {
        $updatedState = $this->enricher->enrich(
            $this->createTestDeliveryExecution(),
            'test',
            $this->getItemData(),
        );
        $this->assertNotEquals($updatedState, $this->getItemData());
        $this->assertEquals(200, $updatedState['data']['body']['elements'][0]['properties']['height']);
        $this->assertStringContainsString(
            '/api/v1/asset?path=deliveryId%2Ftest%2Fbase64_decoded_a31eafe55ebb25808079fdec50f3cb41.png',
            $updatedState['data']['body']['elements'][0]['properties']['content-taomedia:testKey'],
        );
    }

    public function testSkipPreviousCompiled()
    {
        $encodeImage = 'data:application/pdf;base64,';
        $updatedState = $this->enricher->enrich(
            $this->createTestDeliveryExecution(),
            'test',
            $this->getItemData($encodeImage),
        );
        $this->assertEquals($updatedState, $this->getItemData($encodeImage));
        $this->assertEquals(200, $updatedState['data']['body']['elements'][0]['properties']['height']);
        $this->assertStringContainsString(
            $encodeImage,
            $updatedState['data']['body']['elements'][0]['properties']['content-taomedia:testKey'],
        );
    }

    private function getItemData(string $value = 'base64_decoded_a31eafe55ebb25808079fdec50f3cb41.png'): array
    {
        return [
            'data' => [
                'body' => [
                    'elements' => [
                        [
                            'properties' => [
                                'content-taomedia:testKey' => $value,
                                'height' => 200,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
