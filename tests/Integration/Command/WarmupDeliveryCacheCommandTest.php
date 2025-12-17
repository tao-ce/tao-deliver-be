<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\WarmupDeliveryCacheCommand;
use App\Domain\Delivery\Model\Delivery;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Repository\DeliveryRepository;
use App\Tests\Traits\CacheTestingTrait;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Traits\FilesystemTrait;
use Google\Cloud\Core\Lock\LockInterface;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use qtism\data\storage\xml\XmlCompactDocument;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;

class WarmupDeliveryCacheCommandTest extends KernelTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use FilesystemTrait;
    use CacheTestingTrait;
    use QtiTestingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->setUpTestCache();
        $this->setUpTestDocumentManager();
    }

    public function testItWarmsUpTheCache(): void
    {
        $deliveryId = 'Basic';

        $commandTester = new CommandTester($this->createCommand($deliveryId));

        $commandTester->execute(
            [
                'deliveryId' => $deliveryId,
            ],
        );

        self::assertInstanceOf(
            XmlCompactDocument::class,
            $this->getFromCache(
                $this->getCacheKey(
                    $deliveryId,
                    null,
                    QtiPackageCompiler::COMPACT_TEST_FILE_NAME,
                ),
            ),
        );

        foreach (['Item-Q01', 'Item-Q02', 'Item-Q03'] as $itemIdentifier) {
            self::assertIsArray(
                $this->getFromCache(
                    $this->getCacheKey(
                        $deliveryId,
                        $itemIdentifier,
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ),
            );
            self::assertIsArray(
                $this->getFromCache(
                    $this->getCacheKey(
                        $deliveryId,
                        $itemIdentifier,
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ),
            );
        }
    }

    public function testItCanBeLocked(): void
    {
        $deliveryId = 'Basic';

        $commandTester = new CommandTester($this->createCommand($deliveryId));

        /** @var LockInterface $lock */
        $lock = static::getContainer()->get(LockFactory::class)->createLock('locks:delivery-cache-warmup:' . $deliveryId);

        if ($lock->acquire()) {
            $commandTester->execute(
                [
                    'deliveryId' => $deliveryId,
                ],
            );

            $this->assertStringContainsString(
                'The command is already running in locked mode.',
                $commandTester->getDisplay(),
            );

            $lock->release();
        }
    }

    private function createCommand(string $deliveryId): WarmupDeliveryCacheCommand
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q01/portableElements.json',
            'Item-Q02/item.json',
            'Item-Q02/portableElements.json',
            'Item-Q03/item.json',
            'Item-Q03/portableElements.json',
        ]);

        $delivery = $this->createTestDelivery(
            $deliveryId,
            '1',
            'Basic/compact-test.xml',
        );

        $this->saveDocument($delivery);

        $repository = $this->getMockBuilder(DeliveryRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $repository->method('find')
            ->with($deliveryId)
            ->willReturn($delivery);

        return new WarmupDeliveryCacheCommand(
            static::getContainer()->get('qti_compiled_deliveries.storage'),
            $repository,
            static::getContainer()->get(LockFactory::class),
            static::getContainer()->get(CacheInterface::class),
        );
    }
}
