<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\DocumentManager\Filter\DeliveryExecution\DeliveryExecutionCollectionFilterFactory;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryExecutionRepository;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Handler\DocumentHandlerInterface;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeliveryExecutionRepositoryIsExistsForUserIdAndStatusesTest extends TestCase
{
    private DocumentManagerInterface|MockObject $manager;
    private DeliveryExecutionCollectionFilterFactory|MockObject $filterFactory;
    private DeliveryExecutionRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->createMock(DocumentManagerInterface::class);
        $this->filterFactory = $this->createMock(DeliveryExecutionCollectionFilterFactory::class);

        // The constructor requires LtiCustomSettings, but it is not used by existsForUserIdAndStatuses.
        // Use a simple stub to satisfy the type.
        $ltiCustomSettings = $this->createStub(\App\Lti\LtiCustomSettings::class);

        $this->subject = new DeliveryExecutionRepository(
            $this->manager,
            $this->filterFactory,
            $ltiCustomSettings,
        );
    }

    public function testItReturnsTrueWhenAtLeastOneDocumentMatches(): void
    {
        $driver = $this->createMock(DocumentDriverInterface::class);

        $documentDriverData = $this->createMock(DocumentDriverDataInterface::class);


        $driver
            ->expects($this->once())
            ->method('getDocumentsCollectionData')
            ->with(
                'deliveries',
                ['criteria'],
                1,
            )
            ->willReturn((new \ArrayObject([$documentDriverData]))->getIterator());

        $handler = $this->createMock(DocumentHandlerInterface::class);

        $connection = $this->createMock(\OAT\Bundle\DocumentManagerBundle\Connection\DocumentConnectionInterface::class);
        $connection->method('getDriver')->willReturn($driver);

        $handler->method('getConnection')->willReturn($connection);
        $handler->method('getHandledClassConfiguration')->with(DeliveryExecution::class)->willReturn(['storageName' => 'deliveries']);

        $this->manager->method('getHandlerForClass')->with(DeliveryExecution::class)->willReturn($handler);


        $filter = $this->createMock(\OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface::class);
        $filter->method('getFilter')->willReturn(['criteria']);

        $this->filterFactory
            ->expects($this->once())
            ->method('createForUserIdAndStatuses')
            ->with($driver, 'user-1', [DeliveryExecution::STATUS_INTERACTING])
            ->willReturn($filter);




        $this->assertTrue($this->subject->existsForUserIdAndStatuses('user-1', [DeliveryExecution::STATUS_INTERACTING]));
    }

    public function testItReturnsFalseWhenNoDocumentsMatch(): void
    {
        $driver = $this->createMock(DocumentDriverInterface::class);
        $driver
            ->expects($this->once())
            ->method('getDocumentsCollectionData')
            ->with(
                'deliveries',
                ['criteria'],
                1,
            )
            ->willReturn(new \EmptyIterator());

        $handler = $this->createMock(DocumentHandlerInterface::class);

        $connection = $this->createMock(\OAT\Bundle\DocumentManagerBundle\Connection\DocumentConnectionInterface::class);
        $connection->method('getDriver')->willReturn($driver);

        $handler->method('getConnection')->willReturn($connection);
        $handler->method('getHandledClassConfiguration')->with(DeliveryExecution::class)->willReturn(['storageName' => 'deliveries']);

        $this->manager->method('getHandlerForClass')->with(DeliveryExecution::class)->willReturn($handler);


        $filter = $this->createMock(\OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface::class);
        $filter->method('getFilter')->willReturn(['criteria']);

        $this->filterFactory
            ->expects($this->once())
            ->method('createForUserIdAndStatuses')
            ->with($driver, 'user-1', [DeliveryExecution::STATUS_INTERACTING])
            ->willReturn($filter);




        $this->assertFalse($this->subject->existsForUserIdAndStatuses('user-1', [DeliveryExecution::STATUS_INTERACTING]));
    }
}
