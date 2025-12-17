<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ItemEnricher;

use App\TestRunner\ItemEnricher\{Contract\ItemDataEnricherInterface, ModifyAssetsLinksEnricher};
use App\Tests\Traits\DomainTestingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ModifyAssetsLinksEnricherTest extends KernelTestCase
{
    use DomainTestingTrait;

    private ItemDataEnricherInterface $enricher;

    public function setUp(): void
    {
        static::bootKernel();
        $this->enricher = static::getContainer()->get(ModifyAssetsLinksEnricher::class);
    }

    public function testItemDataIsParsedAndReplaced()
    {
        $updatedState = $this->enricher->enrich(
            $this->createTestDeliveryExecution(),
            'test',
            $this->getItemData(),
        );
        $this->assertNotEquals($updatedState, $this->getItemData());
        $this->assertStringContainsString(
            sprintf('%s/api/v1/asset?path=', '//tao_deliver_be_nginx'),
            $updatedState['assets'][0]['testKey'],
        );
    }

    private function getItemData(): array
    {
        return [
            'assets' => [
                [
                    'testKey' => 'testValue',
                ],
            ],
        ];
    }
}
