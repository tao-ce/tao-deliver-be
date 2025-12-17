<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Tests\Helpers\ContainerAwareTestingHelper;
use OAT\Bundle\DocumentManagerBundle\Document\Collection\DocumentCollectionInterface;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use Throwable;

trait DocumentTestingTrait
{
    /** @var DocumentManagerInterface */
    private $testDocumentManager;

    protected function setUp(): void
    {
        $this->setUpTestDocumentManager();
    }

    protected function setUpTestDocumentManager(): void
    {
        ContainerAwareTestingHelper::checkKernelTestCase(static::class);

        $this->testDocumentManager = static::getContainer()->get(DocumentManagerInterface::class);
    }

    protected function saveDocument(DocumentInterface $document): void
    {
        $this->testDocumentManager
            ->getRepositoryForClass(get_class($document))
            ->save($document);
    }

    protected function saveDocumentCollection(DocumentCollectionInterface $documentCollection): void
    {
        foreach ($documentCollection as $document) {
            $this->saveDocument($document);
        }
    }

    protected function findDocumentById(string $documentClass, string $documentId): DocumentInterface
    {
        return $this->testDocumentManager
            ->getRepositoryForClass($documentClass)
            ->find($documentId);
    }

    protected function findDocumentCollection(string $documentClass, DocumentCollectionFilterInterface $filters): DocumentCollectionInterface
    {
        return $this->testDocumentManager
            ->getRepositoryForClass($documentClass)
            ->findCollection($filters);
    }

    protected function assertHasDocumentWithId(string $documentClass, string $documentId): void
    {
        try {
            $document = $this->findDocumentById($documentClass, $documentId);

            $this->assertInstanceOf(DocumentInterface::class, $document);
            $this->assertEquals($documentId, $document->getId());
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage());
        }
    }

    protected function assertHasNoDocumentWithId(string $documentClass, string $documentId): void
    {
        try {
            $this->findDocumentById($documentClass, $documentId);

            $this->fail(sprintf(
                'A document with class %s and id %s was found, but none was expected',
                $documentClass,
                $documentId,
            ));
        } catch (DocumentNotFoundException $exception) {
            $this->assertEquals(
                sprintf("Document class '%s' with id '%s' not found", $documentClass, $documentId),
                $exception->getMessage(),
            );
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage());
        }
    }
}
