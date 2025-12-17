<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use PHPUnit\Framework\TestCase;
use App\DocumentManager\Normalizer\EnrollmentNormalizer;
use App\Domain\Enrollment\Model\Enrollment;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use Exception;

class EnrollmentNormalizerTest extends TestCase
{
    private EnrollmentNormalizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new EnrollmentNormalizer();
    }

    public function testDenormalizeDocumentSuccessfully(): void
    {
        $documentData = $this->createMock(DocumentDriverDataInterface::class);
        $documentData->method('getId')->willReturn('test_id');
        $documentData->method('getData')->willReturn([
            'campaignId' => 'campaign_1',
            'campaignName' => 'Campaign One',
            'sessionId' => 'session_1',
            'sessionName' => 'Session One',
            'sessionTemplateId' => 'template_1',
            'sessionTemplateName' => 'template name',
            'testCategory' => ['id' => 'cat_1', 'name' => 'Category A'],
        ]);

        $document = $this->subject->denormalizeDocument($documentData, Enrollment::class);

        $this->assertInstanceOf(Enrollment::class, $document);
        $this->assertEquals('test_id', $document->getId());
        $this->assertEquals('campaign_1', $document->getCampaignId());
        $this->assertEquals('Campaign One', $document->getCampaignName());
        $this->assertEquals('session_1', $document->getSessionId());
        $this->assertEquals('Session One', $document->getSessionName());
        $this->assertEquals('template_1', $document->getSessionTemplateId());
        $this->assertEquals('template name', $document->getSessionTemplateName());
        $this->assertEquals(['id' => 'cat_1', 'name' => 'Category A'], $document->getTestCategory());
    }

    public function testDenormalizeDocumentThrowsException(): void
    {
        $documentData = $this->createMock(DocumentDriverDataInterface::class);
        $documentData->method('getId')->willReturn('test_id');
        $documentData->method('getData')->willThrowException(new Exception('Mocked failure'));

        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize enrollment:test_id Error: Mocked failure');

        $this->subject->denormalizeDocument($documentData, Enrollment::class);
    }

    public function testNormalizeDocumentSuccessfully(): void
    {
        $document = $this->createMock(Enrollment::class);
        $document->method('getId')->willReturn('test_id');

        $normalizedData = $this->subject->normalizeDocument($document);

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $normalizedData);
        $this->assertEquals('test_id', $normalizedData->getId());
    }

    public function testSupportsReturnsTrueForEnrollmentClass(): void
    {
        $documentDriver = $this->createMock(DocumentDriverInterface::class);

        $this->assertTrue($this->subject->supports($documentDriver, Enrollment::class));
    }

    public function testSupportsReturnsFalseForOtherClasses(): void
    {
        $documentDriver = $this->createMock(DocumentDriverInterface::class);

        $this->assertFalse($this->subject->supports($documentDriver, stdClass::class));
    }
}
