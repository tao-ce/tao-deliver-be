<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ItemEnricher;

use App\TestRunner\ItemEnricher\{Contract\ItemStateEnricherInterface, FileNameAssignEnricher};
use qtism\common\datatypes\files\FileHash;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FileNameAssignEnricherTest extends KernelTestCase
{
    private ItemStateEnricherInterface $enricher;

    public function setUp(): void
    {
        static::bootKernel();
        $this->enricher = static::getContainer()->get(FileNameAssignEnricher::class);
    }

    public function testUnknownStateSkipped(): void
    {
        $state = 'not an object';
        $this->assertSame($state, $this->enricher->enrich($state));
    }

    public function testStateIsParsedAndReplaced(): void
    {
        $updatedState = $this->enricher->enrich($this->getState());
        $this->assertNotEquals($updatedState, $this->getState());
        $this->assertNotEmpty($updatedState['response']['base'][FileHash::FILE_HASH_KEY]['link']);
        $this->assertNotEmpty($updatedState['response']['base'][FileHash::FILE_HASH_KEY]['downloadUrl']);
        $this->assertEquals(
            $updatedState['response']['base'][FileHash::FILE_HASH_KEY]['link'],
            $updatedState['response']['base'][FileHash::FILE_HASH_KEY]['downloadUrl'],
        );
    }

    private function getState(): array
    {
        return [
            'response' => [
                'base' => [
                    FileHash::FILE_HASH_KEY => [
                        'name' => 'fileTestName',
                        'id' => 'test',
                    ],
                ],
            ],
        ];
    }
}
