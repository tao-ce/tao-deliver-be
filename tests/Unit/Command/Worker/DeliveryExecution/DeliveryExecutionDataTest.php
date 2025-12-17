<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Command\Worker\DeliveryExecution;

use App\Command\DeliveryExecution\DeliveryExecutionData;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryRepository;
use App\Serializer\Normalizer\DeliveryExecutionNormalizer;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use ZipArchive;

class DeliveryExecutionDataTest extends TestCase
{
    private const CONSOLE_COMMAND_NAME = 'delivery-execution:data';
    private const QTI_COMPILED_DIR = '/tmp';
    private MockObject|RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionServiceMock;
    private MockObject|DeliveryExecutionNormalizer $deliveryExecutionNormalizerMock;
    private MockObject|DeliveryRepository $deliveryRepositoryMock;
    private MockObject|DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private MockObject|ZipArchive $zipArchiveMock;

    private DeliveryExecutionData $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zipArchiveMock = $this->createMock(ZipArchive::class);
        $this->deliveryExecutionServiceMock = $this->createMock(
            RepositoryAwareDeliveryExecutionServiceInterface::class,
        );
        $this->deliveryExecutionNormalizerMock = $this->createMock(DeliveryExecutionNormalizer::class);
        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $this->deliveryExecutionPropertyService = $this->createMock(DeliveryExecutionPropertyService::class);

        $this->createMockData();

        $this->subject = new DeliveryExecutionData(
            $this->deliveryExecutionServiceMock,
            $this->deliveryExecutionNormalizerMock,
            $this->deliveryRepositoryMock,
            $this->deliveryExecutionPropertyService,
            $this->zipArchiveMock,
            self::QTI_COMPILED_DIR,
        );
    }

    public function testExecute(): void
    {
        $commandTester = $this->executeCommand($this->subject, ['id' => 'your_delivery_execution_id']);
        $this->assertStringContainsString('Delivery execution Data', $commandTester->getDisplay());
    }

    private function executeCommand(DeliveryExecutionData $command, array $options = []): CommandTester
    {
        $application = new Application();
        $application->add($command);

        $command = $application->find(self::CONSOLE_COMMAND_NAME);
        $commandTester = new CommandTester($command);

        $arguments = array_merge([
            'command' => $command->getName(),
        ], $options);

        $commandTester->execute($arguments);

        return $commandTester;
    }

    public function testZipCreated(): void
    {
        $dir = self::QTI_COMPILED_DIR . '/deliveryId';
        if (!is_dir($dir)) {
            mkdir(self::QTI_COMPILED_DIR . '/deliveryId');
        }

        $this->zipArchiveMock->expects(self::once())->method('open')->willReturn(true);

        $this->zipArchiveMock->expects(self::any())->method('addFile');

        $this->zipArchiveMock->expects(self::once())->method('close')->willReturn(true);

        $this->executeCommand($this->subject, [
            'id' => 'userId#deliveryId#resultId#tenantId',
            '--with-qti-compiled-delivery' => true,
        ]);
    }

    public function testExecuteWithDeliveryOption(): void
    {
        $deliveryExecutionId = 'userId#deliveryId#resultId#tenantId';
        $normalizedData = [];

        $this->deliveryExecutionNormalizerMock->expects($this->once())->method('normalize')->willReturn(
            $normalizedData,
        );

        $this->deliveryRepositoryMock->expects($this->once())->method('find')->willReturn(
            $this->createMock(Delivery::class),
        );

        $commandTester = $this->executeCommand($this->subject, [
            'id' => $deliveryExecutionId,
            '--delivery' => true,
        ]);

        $this->assertStringContainsString('deliveryId', $commandTester->getDisplay());
    }

    public function testExecuteMissingArguments(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "id").');

        $this->executeCommand($this->subject);
    }

    public function testExecuteWithDeliveryAndQtiCompiledOptions(): void
    {
        $deliveryExecutionId = 'userId#deliveryId#resultId#tenantId';
        $normalizedData = ['deliveryExecutionId' => 'userId#deliveryId#resultId#tenantId'];
        $qtiCompiledDeliveryZipData = '';
        $this->zipArchiveMock->method('open')->willReturn(true);

        $this->deliveryExecutionNormalizerMock->expects($this->once())->method('normalize')->willReturn(
            $normalizedData,
        );

        $commandTester = $this->executeCommand($this->subject, [
            'id' => $deliveryExecutionId,
            '--delivery' => true,
            '--with-qti-compiled-delivery' => true,
            '--qti-test' => true,
        ]);

        $this->assertStringContainsString('deliveryId', $commandTester->getDisplay());
        $this->assertStringContainsString($qtiCompiledDeliveryZipData, $commandTester->getDisplay());
        $this->assertStringContainsString('qtiTest', $commandTester->getDisplay());
    }

    public function testExecuteWithPrettyOption(): void
    {
        $deliveryExecutionId = 'userId#deliveryId#resultId#tenantId';
        $commandTester = $this->executeCommand($this->subject, [
            'id' => $deliveryExecutionId,
            '--pretty' => true,
        ]);

        $this->assertStringContainsString(
            json_encode(['deliveryExecutionId' => $deliveryExecutionId], JSON_PRETTY_PRINT),
            $commandTester->getDisplay(),
        );
    }

    public function testExecuteWithBase64Option(): void
    {
        $deliveryExecutionId = 'userId#deliveryId#resultId#tenantId';
        $commandTester = $this->executeCommand($this->subject, [
            'id' => $deliveryExecutionId,
            '--base64' => true,
        ]);

        // Assert expectations for handling --base64 option
        $this->assertStringContainsString(
            base64_encode(json_encode(['deliveryExecutionId' => $deliveryExecutionId])),
            $commandTester->getDisplay(),
        );
    }


    private function createMockData(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getId')->willReturn('userId#deliveryId#resultId#tenantId');
        $deliveryExecution->method('getDeliveryId')->willReturn('deliveryId');
        $deliveryExecution->method('getTenantId')->willReturn('tenantId');

        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getTenantId')->willReturn('tenantId');
        $delivery->method('getId')->willReturn('deliveryId');
        $this->deliveryRepositoryMock->method('find')->willReturn($delivery);

        $this->deliveryExecutionServiceMock->method('findDeliveryExecutionOrFail')->willReturn($deliveryExecution);

        $this->deliveryExecutionNormalizerMock->method('normalize')->willReturn(
            ['deliveryExecutionId' => 'userId#deliveryId#resultId#tenantId'],
        );

        file_put_contents(self::QTI_COMPILED_DIR . '/deliveryId.zip', 'OK');
    }
}
