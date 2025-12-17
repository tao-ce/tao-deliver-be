<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    'delivery-execution:remove-state-copies',
    'Removes duplicate state from itemState of a given delivery execution',
)]
class RemoveStateCopies extends Command
{
    private SymfonyStyle $io;
    private DeliveryExecution $deliveryExecution;

    public function __construct(
        private readonly RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'deliveryExecutionId',
                InputArgument::REQUIRED,
                'The ID of the delivery execution to process',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail(
            $input->getArgument('deliveryExecutionId'),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stateObject = $this->deliveryExecution->getExtraStateData();
        foreach ($stateObject->getItemStates() as $itemId => $itemState) {
            $parsedItemState = json_decode($itemState, true);
            $hasChanges = false;
            foreach ($parsedItemState as &$responseState) {
                if (isset($responseState['response'], $responseState['state']) && $responseState['response'] === $responseState['state']) {
                    unset($responseState['response']);
                    $hasChanges = true;
                }
            }
            if (!$hasChanges) {
                continue;
            }

            $this->io->caution(
                sprintf(
                    "Cleaned up Item State:\n%s",
                    json_encode($parsedItemState, JSON_PRETTY_PRINT),
                ),
            );

            $stateObject = $stateObject->withItemState(
                $itemId,
                json_encode($parsedItemState),
            );
        }

        if (!$this->io->confirm('Continue?', false)) {
            return static::INVALID;
        }
        $this->deliveryExecution->setExtraStateData($stateObject);
        $this->deliveryExecutionService->saveDeliveryExecution($this->deliveryExecution);
        return static::SUCCESS;
    }
}
