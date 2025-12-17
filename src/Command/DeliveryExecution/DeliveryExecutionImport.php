<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Command\DeliveryExecution;

use App\Delivery\Event\DeliveryCreatedEvent;
use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\Dto\DeliveryExecutionDto;
use App\Service\DeliveryExecution\SynchronizeDeliveryExecutionService;
use Carbon\Carbon;
use Google\Auth\Cache\InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ZipArchive;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'delivery-execution:import',
    description: 'A command to check delivery-execution details',
)]
class DeliveryExecutionImport extends Command
{
    public function __construct(
        private readonly SynchronizeDeliveryExecutionService $createDeliveryExecutionService,
        private readonly DeliveryRepository $deliveryRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ZipArchive $zipArchive,
        private readonly string $qtiCompiledPrefix,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('A command to check delivery-execution details')
            ->addArgument(
                'tenantId',
                InputArgument::REQUIRED,
                'tenantId',
            )
            ->addArgument(
                'encodedDeliveryExecutionData',
                InputArgument::REQUIRED,
                'encodedDeliveryExecutionData',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenantId = $input->getArgument('tenantId');
        $deliveryExecutionData = json_decode(
            base64_decode(
                $input->getArgument('encodedDeliveryExecutionData'),
            ),
            true,
        );

        $io->title('Delivery execution import');
        try {
            if (!empty($deliveryExecutionData['qtiCompiledDelivery'])) {
                if (empty($deliveryExecutionData['delivery'])) {
                    throw new InvalidArgumentException('Qti compiled should be export with delivery data');
                }
                $this->extractQtiCompiled(
                    $deliveryExecutionData['qtiCompiledDelivery'],
                    $deliveryExecutionData['delivery']['deliveryId'],
                );
                $io->info('Imported QTI compiled delivery..');
            }

            if (!empty($deliveryExecutionData['delivery'])) {
                $deliveryData = $deliveryExecutionData['delivery'];
                $this->createDelivery(
                    $deliveryData['deliveryId'],
                    $deliveryData['tenantId'],
                    $deliveryData['compactTestFilePath'],
                    $deliveryData['configuration'],
                    $deliveryData['qtiItemsMapping'],
                    $deliveryData['packageRef'],
                );
                $io->info(sprintf('Crated Delivery [%s]', $deliveryData['deliveryId']));
            }

            $this->createDeliveryExecutionService->synchronize(
                DeliveryExecutionDto::createFromArray((array)$deliveryExecutionData),
                $tenantId,
            );

            $io->info(
                sprintf(
                    'Crated Delivery Execution [%s] for tenant [%s]',
                    $deliveryExecutionData['deliveryExecutionId'],
                    $tenantId,
                ),
            );
            $io->write('Created');
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return parent::INVALID;
        }

        return parent::SUCCESS;
    }

    private function createDelivery(
        string $deliveryId,
        string $tenantId,
        string $compactTestFilePath,
        array $configuration,
        array $qtiItemsMapping,
        ?string $packageRef,
    ): Delivery {
        $delivery = new Delivery(
            $deliveryId,
            $tenantId,
            Carbon::now(),
            $compactTestFilePath,
            $configuration,
            $qtiItemsMapping,
            $packageRef,
        );

        $this->deliveryRepository->save($delivery);

        $this->eventDispatcher->dispatch(
            new DeliveryCreatedEvent($delivery),
        );

        return $delivery;
    }

    private function extractQtiCompiled(string $zipEncoded, string $deliveryId): void
    {
        $zipFile = $this->qtiCompiledPrefix . DIRECTORY_SEPARATOR . $deliveryId . '.zip';
        file_put_contents($zipFile, base64_decode($zipEncoded));

        if (true !== $this->zipArchive->open($zipFile)) {
            throw new InvalidArgumentException('Cannot extract qti compile files');
        }

        $this->zipArchive->extractTo($this->qtiCompiledPrefix . DIRECTORY_SEPARATOR . $deliveryId);
        $this->zipArchive->close();
        unlink($zipFile);
    }
}
