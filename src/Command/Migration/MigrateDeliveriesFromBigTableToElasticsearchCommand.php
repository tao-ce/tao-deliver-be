<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Migration;

use App\Domain\Delivery\Model\Delivery;
use App\Helper\Date;
use App\Repository\DeliveryRepository;
use Generator;
use Google\Cloud\Bigtable\BigtableClient;
use Google\Cloud\Bigtable\Exception\BigtableDataOperationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\When;

#[
    When(env: 'worker'),
    AsCommand(
        name: 'app:migrate:deliveries',
    )
]
class MigrateDeliveriesFromBigTableToElasticsearchCommand extends Command
{
    private const ARG_FORCE = 'force';
    private const ARG_GCP_BIG_TABLE_INSTANCE_ID = 'gcpBigTableInstanceId';
    private const ARG_GCP_BIG_TABLE_TABLE_NAME = 'gcpBigTableTableName';

    public function __construct(
        private readonly BigtableClient $bigTableClient,
        private readonly DeliveryRepository $deliveryRepository,
        private readonly string $gcpBigTableInstanceId,
        private readonly string $deliveriesDocumentStorageName,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('A command for migrating deliveries from BigTable to Elasticsearch.')
            ->addOption(
                self::ARG_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Performs the migration',
            )
            ->addOption(
                self::ARG_GCP_BIG_TABLE_INSTANCE_ID,
                null,
                InputOption::VALUE_OPTIONAL,
                'GCP BigTable Instance ID',
                $this->gcpBigTableInstanceId,
            )
            ->addOption(
                self::ARG_GCP_BIG_TABLE_TABLE_NAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'GCP BigTable Table Name',
                $this->deliveriesDocumentStorageName,
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $style->info('Starting migration...');

        $deliveriesCollection = $this->getDeliveriesFromBigTable(
            $input->getOption(self::ARG_GCP_BIG_TABLE_INSTANCE_ID),
            $input->getOption(self::ARG_GCP_BIG_TABLE_TABLE_NAME),
        );
        $deliveriesArray = iterator_to_array($deliveriesCollection);

        $style->info(sprintf('Found %d deliveries in BigTable. Preparing document collection...', count($deliveriesArray)));
        $style->progressStart(count($deliveriesArray));
        foreach ($deliveriesArray as $id => $content) {
            $version = $this->getLatestVersionOfDelivery($content['data']['versions']);
            $delivery = new Delivery(
                (string)$id,
                $content['data']['tenantId'][0]['value'],
                Date::createFromDefaultFormat($content['data']['createdAt'][0]['value']),
                $version['compactTestFilePath'],
                $version['configuration'],
                json_decode($content['data']['qtiItemsMapping'][0]['value'], true, 512, JSON_THROW_ON_ERROR),
            );

            $style->progressAdvance();

            if (!$input->getOption(self::ARG_FORCE)) {
                continue;
            }

            $this->deliveryRepository->save($delivery);
        }

        $style->progressFinish();

        if (!$input->getOption(self::ARG_FORCE)) {
            $style->warning(sprintf(
                'Successfully prepared %d deliveries for migration. Use --force option to perform it.',
                count($deliveriesArray),
            ));

            return Command::SUCCESS;
        }

        $style->success(sprintf('Successfully migrated %d deliveries into Elasticsearch', count($deliveriesArray)));

        return Command::SUCCESS;
    }

    /**
     * @throws BigtableDataOperationException
     */
    private function getDeliveriesFromBigTable(
        string $gcpBigTableInstanceId,
        string $gcpBigTableTableName,
    ): Generator {
        return $this->bigTableClient
            ->table(
                $gcpBigTableInstanceId,
                $gcpBigTableTableName,
            )
            ->readRows()
            ->readAll();
    }

    private function getLatestVersionOfDelivery(array $deliveryVersions): array
    {
        $versions = json_decode($deliveryVersions[0]['value'], true, 512, JSON_THROW_ON_ERROR);

        ksort($versions);

        return array_pop($versions);
    }
}
