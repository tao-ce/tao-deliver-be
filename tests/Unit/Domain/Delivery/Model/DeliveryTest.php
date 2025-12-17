<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Delivery\Model;

use App\Domain\Delivery\Model\Delivery;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use PHPUnit\Framework\TestCase;

class DeliveryTest extends TestCase
{
    private const ID = 'id';
    private const TENANT_ID = 'tenantId';
    private const COMPACT_TEST_FILE_PATH = 'compactTestFilePath';
    private const CONFIGURATION = ['config' => 'foo', 'metadata' => self::METADATA];
    private const METADATA = [
        'property1' => ['property1_value1', 'property1_value2'],
        'property2' => ['property2_value1'],
    ];
    private const QTI_ITEMS_MAPPING = ['qtiItemsMapping' => 'bar'];
    private const PACKAGE_REF = 'https://example.com/qti.zip';

    private Delivery $subject;
    private CarbonInterface $now;

    protected function setUp(): void
    {
        $this->now = Carbon::now();

        Carbon::setTestNow($this->now);

        $this->subject = new Delivery(
            self::ID,
            self::TENANT_ID,
            $this->now,
            self::COMPACT_TEST_FILE_PATH,
            self::CONFIGURATION,
            self::QTI_ITEMS_MAPPING,
            self::PACKAGE_REF,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testItImplementsDocumentInterface(): void
    {
        $this->assertInstanceOf(DocumentInterface::class, $this->subject);
    }

    public function testItCanRetrieveTheId(): void
    {
        $this->assertEquals(self::ID, $this->subject->getId());
    }

    public function testItCanRetrieveTheTenantId(): void
    {
        $this->assertEquals(self::TENANT_ID, $this->subject->getTenantId());
    }

    public function testItCanRetrieveTheCreationDate(): void
    {
        $this->assertEquals($this->now, $this->subject->getCreatedAt());
    }

    public function testItCanRetrieveTheConfiguration(): void
    {
        $this->assertEquals(self::CONFIGURATION, $this->subject->getConfiguration());
    }

    public function testItCanRetrieveTheCompactTestFilePath(): void
    {
        $this->assertEquals(self::COMPACT_TEST_FILE_PATH, $this->subject->getQtiCompactTestFilePath());
    }

    public function testItCanRetrieveMetadata(): void
    {
        $this->assertSame(self::METADATA, $this->subject->getMetadata());
    }

    public function testItCanRetrieveMetadataPropertyValues(): void
    {
        foreach (self::METADATA as $property => $values) {
            $this->assertSame($values, $this->subject->getMetadataPropertyValues($property));
        }
    }

    public function testItCanRetrieveEmptyMetadataPropertyValues(): void
    {
        $this->assertSame([], $this->subject->getMetadataPropertyValues('unknown'));
    }

    public function testItCanRetrieveMetadataPropertyValue(): void
    {
        foreach (self::METADATA as $property => $values) {
            $this->assertSame(reset($values), $this->subject->getMetadataPropertyValue($property));
        }
    }

    public function testItCanRetrieveEmptyMetadataPropertyValue(): void
    {
        $this->assertNull($this->subject->getMetadataPropertyValue('unknown'));
    }

    public function testItCanPreserveMetadataProperties(): void
    {
        $this->subject->setConfiguration(['some_property' => 'some_value']);
        $this->assertEquals(self::METADATA, $this->subject->getMetadata());
    }

    public function testItCanRewriteMetadataProperties(): void
    {
        $metadata = ['property' => ['value']];
        $this->subject->setConfiguration(['metadata' => $metadata]);
        $this->assertEquals($metadata, $this->subject->getMetadata());
    }

    public function testItCanRetrievePackageRef(): void
    {
        $this->assertSame(self::PACKAGE_REF, $this->subject->getPackageRef());
    }

    public function testIsMultiLanguageReturnsFalseWhenOnlyOneSupportedLocale(): void
    {
        $this->subject->setSupportedLocales(['en-US']);

        $this->assertFalse($this->subject->isMultiLanguage());
    }

    public function testIsMultiLanguageReturnsTrueWhenMultipleSupportedLocales(): void
    {
        $this->subject->setSupportedLocales(['en-US', 'fr-FR']);

        $this->assertTrue($this->subject->isMultiLanguage());
    }

    public function testIsMultiLanguageReturnsFalseWhenMainLocaleIsTheOnlySupportedLocale(): void
    {
        $this->subject->setMainLocale('en-US');
        $this->subject->setSupportedLocales(['en-US']);

        $this->assertFalse($this->subject->isMultiLanguage());
    }

    public function testIsMultiLanguageReturnsTrueWhenMainLocaleDiffersFromTheSupportedLocales(): void
    {
        $this->subject->setMainLocale('en-US');
        $this->subject->setSupportedLocales(['fr-FR']);

        $this->assertTrue($this->subject->isMultiLanguage());
    }

    public function testIsMultiLanguageReturnsTrueWhenMainLocaleIsNotTheOnlySupportedLocale(): void
    {
        $this->subject->setMainLocale('en-US');
        $this->subject->setSupportedLocales(['en-US', 'fr-FR']);

        $this->assertTrue($this->subject->isMultiLanguage());
    }
}
