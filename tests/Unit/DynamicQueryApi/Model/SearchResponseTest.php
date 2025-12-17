<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DynamicQueryApi\Model;

use App\DynamicQueryApi\Model\SearchResponse;
use PHPUnit\Framework\TestCase;

class SearchResponseTest extends TestCase
{
    public function testGetters(): void
    {
        $searchResponse = new SearchResponse(['foo'], 5, ['bar']);

        $this->assertSame(['foo'], $searchResponse->getData());
        $this->assertSame(5, $searchResponse->getTotalResults());
        $this->assertSame(['bar'], $searchResponse->getLastId());
    }
}
