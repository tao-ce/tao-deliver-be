<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Repository\DeliveryExecutionAlias;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionAlias;
use App\Repository\DeliveryExecutionAlias\DeliveryExecutionIdentifierAliasRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionIdentifierAliasRepositoryTest extends TestCase
{
    private const EXPECTED_TENANT_ID = 'tenantId';
    private const EXPECTED_ALIAS = 'alias';
    private const EXPECTED_DELIVERY_EXECUTION_ID = 'deliveryExecutionId';
    private DeliveryExecutionIdentifierAliasRepository $subject;


    public function testSuccessfulSave(): void
    {
        $this->subject = $this->getMockBuilder(DeliveryExecutionIdentifierAliasRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['save', 'find', 'findDeliveryExecutionId'])
            ->getMock();

        $this->subject->expects($this->once())->method('findDeliveryExecutionId')->willReturn(null);
        $this->subject->expects($this->once())->method('save');

        $this->subject->saveDeliveryExecutionId(
            self::EXPECTED_TENANT_ID,
            self::EXPECTED_ALIAS,
            self::EXPECTED_DELIVERY_EXECUTION_ID,
        );

        $this->assertTrue(true);
    }

    public function testFailedSave()
    {
        $this->subject = $this->getMockBuilder(DeliveryExecutionIdentifierAliasRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['save', 'find', 'findDeliveryExecutionId'])
            ->getMock();

        $this->subject->method('findDeliveryExecutionId')
            ->willReturn('otherDeliveryExecutionId');

        $this->expectException(InvalidArgumentException::class);

        $this->subject->saveDeliveryExecutionId(
            self::EXPECTED_TENANT_ID,
            self::EXPECTED_ALIAS,
            self::EXPECTED_DELIVERY_EXECUTION_ID,
        );
    }

    public function testSuccessfulFindRecord()
    {
        $this->subject = $this->getMockBuilder(DeliveryExecutionIdentifierAliasRepository::class)
            ->disableOriginalConstructor()
            ->setMethods(['find'])
            ->getMock();

        $this->subject->method('find')
            ->willReturn(
                new DeliveryExecutionAlias(
                    $this->getDocumentId(),
                    self::EXPECTED_DELIVERY_EXECUTION_ID,
                ),
            );
        $result = $this->subject->findDeliveryExecutionId(self::EXPECTED_TENANT_ID, self::EXPECTED_ALIAS);
        self::assertEquals(self::EXPECTED_DELIVERY_EXECUTION_ID, $result);
    }

    public function testSuccessDeleted(): void
    {
        $this->subject = $this->createPartialMock(DeliveryExecutionIdentifierAliasRepository::class, ['delete']);
        $this->subject
            ->expects($this->once())
            ->method('delete')
            ->with(new DeliveryExecutionAlias($this->getDocumentId(), null));
        $this->subject->deleteDeliveryExecutionId($this->getDocumentId());
    }

    private function getDocumentId(): string
    {
        return sprintf('%s#%s', self::EXPECTED_ALIAS, self::EXPECTED_TENANT_ID);
    }
}
