<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'delivery-execution:control-temp-item-state',
)]
class ControlTempItemStateCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly RepositoryAwareDeliveryExecutionServiceInterface $loggerAwareDeliveryExecutionService,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('A command for generation CSV file with results by delivery ID.')
            ->addArgument('deliveryExecutionId', InputArgument::REQUIRED, 'Delivery execution ID')
            ->addArgument('items', InputArgument::REQUIRED, 'Comma separated list of item IDs')
            ->addOption(
                'states',
                's',
                InputOption::VALUE_OPTIONAL,
                'Comma separated list of temporary item states. Should correspond provided items',
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->title('Removing temporary item state from delivery execution');

        $deliveryExecutionId = $input->getArgument('deliveryExecutionId');
        $items = $this->extractArrayFromCommaSeparatedString($input->getArgument('items'));
        $states =  $this->extractArrayFromCommaSeparatedString($input->getOption('states') ?? '');

        $this->io->progressStart(count($items));
        $deliveryExecution = $this->loggerAwareDeliveryExecutionService->findDeliveryExecutionOrFail(
            $deliveryExecutionId,
        );

        if (empty($states)) {
            $this->dropTempStates($deliveryExecution, $items);
        } else {
            $this->updateTempItemStates($deliveryExecution, $items, $states);
        }

        $this->loggerAwareDeliveryExecutionService->saveDeliveryExecution($deliveryExecution);
        $this->io->progressFinish();

        return parent::SUCCESS;
    }

    private function dropTempStates(DeliveryExecution $deliveryExecution, array $items): void
    {
        foreach ($items as $itemIdentifier) {
            $deliveryExecution->removeTemporaryItemState($itemIdentifier);
            $this->io->progressAdvance();
        }
    }

    private function updateTempItemStates(DeliveryExecution $deliveryExecution, array $items, array $states): void
    {
        if (count($items) !== count($states)) {
            throw new \InvalidArgumentException(
                'Number of items and states should be equal, otherwise use only `items` argument to drop states',
            );
        }
        foreach ($items as $index => $itemIdentifier) {
            $deliveryExecution->addTemporaryItemState($itemIdentifier, $states[$index]);
            $this->io->progressAdvance();
        }
    }

    private function extractArrayFromCommaSeparatedString(string $string): array
    {
        return array_filter(explode(',', $string));
    }
}
