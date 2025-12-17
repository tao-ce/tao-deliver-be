<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Datastore;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\UuidGenerator;
use App\Messenger\Message\DataStoreMessage;
use App\Messenger\Message\ResultExtractionMessage;
use App\Repository\DeliveryExecutionRepository;
use Elastic\Elasticsearch\Client;
use Exception;
use Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'datastore:resend-results',
    description: 'Resend all result for deliveryExecution related with given deliveryId or deliveryExecutionId',
)]
class ResendResults extends Command
{
    private const DEFAULT_PAGE_SIZE = 500;
    private SymfonyStyle $io;
    private bool $deliveryMode;
    private array $targetIdentifiers;
    private bool $force;
    private int $pageSize;
    private int $countRecordsForProcessing;
    private ?string $offsetDE = null;
    private bool $forceClosure;

    public function __construct(
        private readonly DeliveryExecutionRepository $deliveryExecutionRepository,
        private readonly Client $elasticClient,
        private readonly DataStoreSenderInterface $resultSender,
        private readonly MessageBusInterface $messageBus,
        private readonly UuidGenerator $uuidGenerator,
        private readonly string $dataStoreDeliveryExecutionIndex,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                <<<'EOF'
            Resend all result for deliveryExecution related with given deliveryId or deliveryExecutionId:
                - If --delivery is provided resend will be triggered for DE related to deliveryIds provided via arguments
                - If --delivery not provided then all identifiers will be considered as delivery execution identifiers
            Example:
                - php bin/console datastore:resend-results <identifier1> <identifier2> [--delivery] [--force] [--force-closure] [--page-size <int>|500] [--offset-de <string>]
            EOF,
            )
            ->addArgument(
                'identifiers',
                InputArgument::IS_ARRAY,
                'Identifiers for reprocessing',
            )
            ->addOption(
                'delivery',
                null,
                InputOption::VALUE_NONE,
                'Flag for delivery mode activation, all identifiers provided via arguments should be considered as DeliverId',
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'flag to launch execution in wet run mode',
            )
            ->addOption(
                'force-closure',
                null,
                InputOption::VALUE_NONE,
                'flag to launch resending with closure of not closed executions',
            )
            ->addOption(
                'page-size',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Number of records to fetch per page (default: 500)',
            )
            ->addOption(
                'offset-de',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Delivery execution ID to start from (for resuming)',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->deliveryMode = (bool)($input->getOption('delivery') ?? false);
        $this->force = (bool)($input->getOption('force') ?? false);
        $this->targetIdentifiers = $input->getArgument('identifiers');
        $this->pageSize = (int)($input->getOption('page-size') ?? self::DEFAULT_PAGE_SIZE);
        $this->offsetDE = $input->getOption('offset-de');
        $this->forceClosure = (bool)($input->getOption('force-closure') ?? false);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->getDeliveryExecutions() as $deliveryExecutionId) {
            $this->countRecordsForProcessing--;
            try {
                /** @var DeliveryExecution $deliveryExecution */
                $deliveryExecution = $this->deliveryExecutionRepository->find($deliveryExecutionId);
                if (!$this->forceClosure && $deliveryExecution->getFinishedAt() === null) {
                    $this->io->warning(
                        sprintf(
                            'Result wasn\'t sent for DE %s, execution wasn\'t finished',
                            $deliveryExecutionId,
                        ),
                    );
                    $this->io->info(
                        sprintf(
                            '[%s] DE was handled. [%d] DEs left',
                            $deliveryExecution->getId(),
                            $this->countRecordsForProcessing,
                        ),
                    );
                    continue;
                }

                if ($this->force) {
                    if ($this->forceClosure) {
                        $this->messageBus->dispatch(new ResultExtractionMessage(
                            $this->uuidGenerator->generate(),
                            $deliveryExecution->getId(),
                            true,
                        ));
                    } else {
                        $this->resultSender->send($deliveryExecution);
                    }
                }
                $this->io->info(
                    sprintf(
                        '[%s] DE was handled. [%d] DEs left',
                        $deliveryExecution->getId(),
                        $this->countRecordsForProcessing,
                    ),
                );

                unset($deliveryExecution);
            } catch (Exception $e) {
                $this->io->warning(
                    sprintf(
                        'An error occurred while sending results for delivery execution %s: %s',
                        $deliveryExecution->getId(),
                        $e->getMessage(),
                    ),
                );
            }
        }
        return Command::SUCCESS;
    }

    private function getDeliveryExecutions(): Generator
    {
        if ($this->deliveryMode === false) {
            $this->countRecordsForProcessing = count($this->targetIdentifiers);
            foreach ($this->targetIdentifiers as $identifier) {
                yield $identifier;
            }
        } else {
            $query = [
                'bool' => [
                    'filter' => [
                        [
                            'terms' => [
                                'deliveryId' => $this->targetIdentifiers,
                            ],
                        ],
                    ],
                ],

            ];

            $fetchCountQuery = $query;
            if ($this->offsetDE) {
                $fetchCountQuery['bool']['filter'][] = [
                    'range' => [
                        'deliveryExecutionId' => [
                            'gt' => $this->offsetDE,
                        ],
                    ],
                ];
            }

            $responseCount = $this->elasticClient->count([
                'index' => $this->dataStoreDeliveryExecutionIndex,
                'body' => ['query' => $fetchCountQuery,],
            ]);
            $this->io->info('Total count of DE: ' . $responseCount['count']);
            $this->countRecordsForProcessing = $responseCount['count'];
            do {
                $fetchQuery = $query;
                if ($this->offsetDE) {
                    $fetchQuery['bool']['filter'][] = [
                        'range' => [
                            'deliveryExecutionId' => [
                                'gt' => $this->offsetDE,
                            ],
                        ],
                    ];
                }
                $result = $this->elasticClient->search([
                    'index' => $this->dataStoreDeliveryExecutionIndex,
                    'body' => [
                        'size' => $this->pageSize,
                        'query' => $fetchQuery,
                        'fields' => [
                            '_id',
                        ],
                        '_source' => false,
                        "sort" => [["deliveryExecutionId" => ['order' => "asc"]]],
                    ],

                ]);
                $records = $result['hits']['hits'];
                $currentHitCount = count($records);
                unset($result);
                foreach ($records as $index => $record) {
                    yield $record['_id'];
                    unset($records[$index]);
                    $this->offsetDE = $record['_id'];
                }
            } while ($currentHitCount > 0);
        }
    }
}
