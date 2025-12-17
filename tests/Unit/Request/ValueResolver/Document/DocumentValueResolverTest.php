<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Request\ValueResolver\Document;

use App\Repository\DeliveryExecutionRepository;
use App\Request\ValueResolver\Document\DocumentValueResolver;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentValueResolverTest extends TestCase
{
    /** @var DocumentValueResolver */
    private $subject;

    /** @var DocumentManagerInterface */
    private $documentManager;

    protected function setUp(): void
    {
        $this->documentManager = $this->createMock(DocumentManagerInterface::class);
        $deliveryExecutionRepository = $this->createMock(DeliveryExecutionRepository::class);
        $this->subject = new DocumentValueResolver($this->documentManager, $deliveryExecutionRepository);
    }

    public function testItCanResolveExistingDocument(): void
    {
        $request = new Request([], [], ['id' => '1']);
        $document = $this->createMock(DocumentInterface::class);
        $document
            ->method('getId')
            ->willReturn('1');

        $this->createRepositoryMock()
            ->method('find')
            ->willReturn($document);

        $result = $this->subject->resolve($request, $this->createArgumentMetadataMock());
        $this->assertIsArray($result);

        /** @var DocumentInterface $documentFromAttributes */
        $documentFromAttributes = $result[0];

        $this->assertInstanceOf(DocumentInterface::class, $documentFromAttributes);
        $this->assertEquals('1', $documentFromAttributes->getId());
    }

    public function testItShouldThrowExceptionOnNotExistingDocument(): void
    {
        $this->createRepositoryMock()
            ->method('find')
            ->willThrowException(new DocumentNotFoundException());

        $this->expectException(NotFoundHttpException::class);
        $this->subject->resolve(new Request(), $this->createArgumentMetadataMock());
    }

    /**
     * @return DocumentRepositoryInterface|MockObject
     */
    private function createRepositoryMock(): DocumentRepositoryInterface
    {
        $repository = $this->createMock(DocumentRepositoryInterface::class);

        $this->documentManager
            ->method('getRepositoryForClass')
            ->willReturn($repository);

        return $repository;
    }

    private function createArgumentMetadataMock(string $className = DocumentInterface::class, string $name = 'document'): ArgumentMetadata
    {
        $paramConverter = $this->createMock(ArgumentMetadata::class);
        $paramConverter->method('getType')->willReturn($className);
        $paramConverter->method('getName')->willReturn($name);

        return $paramConverter;
    }
}
