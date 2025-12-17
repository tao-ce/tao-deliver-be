<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\ResultExtractionMessage;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use Exception;
use Iterator;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\MultipleContainer;
use qtism\runtime\common\OrderedContainer;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\pci\json\Unmarshaller;
use qtism\runtime\tests\AssessmentItemSession;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'session:find-discrepancies',
)]
class FindQtiSessionDiscrepanciesCommand extends Command
{
    public function __construct(
        private readonly DeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly DeliveryExecutionRepository $deliveryExecutionRepository,
        private readonly TestSessionAccessorFactory $testSessionAccessorFactory,
        private readonly Unmarshaller $qtiJsonUnmarshaller,
        private readonly MessageBusInterface $messageBus,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Find all Delivery Executions with mismatching item-state and QTI-session variable values')
            ->addArgument('deliveryId', InputArgument::REQUIRED, 'Delivery ID')
            ->addOption('outputDelimiter', 'd', InputOption::VALUE_OPTIONAL, 'Output Delimiter', ',')
            ->addOption('diffSize', 's', InputOption::VALUE_OPTIONAL, 'Diff size in bytes', 1)
            ->addOption('fix', 'f', InputOption::VALUE_OPTIONAL, description: 'Apply the item-state as session variable values');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deliveryId = $input->getArgument('deliveryId');
        $delimiter = $input->getOption('outputDelimiter');
        $diffSize = (int)$input->getOption('diffSize');
        $isFixRequested = $input->getOption('fix');

        $deliveryExecutions = $this->deliveryExecutionRepository->findByDeliveryId($deliveryId);
        $io->writeln(
            implode(
                $delimiter,
                [
                    'Delivery Execution ID',
                    'Item ID',
                    'Response ID',
                    'Expected value',
                    'Actual value',
                ],
            ),
        );

        foreach ($deliveryExecutions as $deliveryExecution) {
            if ($this->resolveDiffs($deliveryExecution, $diffSize, $delimiter, $io) && $isFixRequested) {
                $this->fix($deliveryExecution);
            }
        }

        return parent::SUCCESS;
    }

    private function resolveDiffs(
        DeliveryExecution $deliveryExecution,
        int $diffSize,
        string $delimiter,
        SymfonyStyle $io,
    ): bool {
        $hasDiffs = false;
        $testSessionAccessor = $this->testSessionAccessorFactory->create($deliveryExecution);
        $session = $testSessionAccessor->retrieve($deliveryExecution->getId());
        foreach ($deliveryExecution->getExtraStateData()->getItemStates() as $itemId => $itemState) {
            try {
                $itemState = json_decode($itemState, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            foreach ($itemState as $variableId => $variableState) {
                if (!isset($variableState['response'])) {
                    continue;
                }

                $itemSessions = $session->getAssessmentItemSessions($itemId)->getArrayCopy();
                /** @var AssessmentItemSession $itemSession */
                $itemSession = end($itemSessions);
                $sessionVariable = $itemSession->getVariable($variableId);
                if (null === $sessionVariable) {
                    continue;
                }

                $value = $this->qtiJsonUnmarshaller->unmarshall($variableState['response']);
                $variable = ResponseVariable::createFromDataModel(
                    $itemSession->getAssessmentItem()->getResponseDeclarations()[$variableId],
                );

                if (($value instanceof MultipleContainer) && $variable->getCardinality() === Cardinality::ORDERED) {
                    $value = new OrderedContainer($value->getBaseType(), $value->getArrayCopy());
                }

                $sessionVariableValues = $sessionVariable->getValue() instanceof Iterator
                    ? $sessionVariable->getValue()
                    : [$sessionVariable->getValue()];
                $values = $value instanceof Iterator
                    ? $value
                    : [$value];
                if (count($sessionVariableValues) !== count($values)) {
                    continue;
                }
                foreach ($sessionVariableValues as $k => $sessionVariableValue) {
                    $expectedValue = $values[$k];
                    if (strlen((string)$expectedValue) - strlen((string)$sessionVariableValue) !== $diffSize) {
                        continue;
                    }
                    $io->writeln(
                        implode(
                            $delimiter,
                            [
                                $deliveryExecution->getId(),
                                $itemId,
                                $variableId,
                                (string)$value,
                                (string)$sessionVariable->getValue(),
                            ],
                        ),
                    );
                    try {
                        $sessionVariable->setValue($value);
                    } catch (Exception $exception) {
                        $io->warning($exception->getMessage());
                        break;
                    }

                    $hasDiffs = true;
                    break;
                }
            }
        }
        $testSessionAccessor->persist($session);

        return $hasDiffs;
    }

    private function fix(mixed $deliveryExecution): void
    {
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        if (null === $deliveryExecution->getFinishedAt()) {
            return;
        }

        $message = new ResultExtractionMessage(
            Uuid::uuid4()->toString(),
            $deliveryExecution->getId(),
        );

        $this->messageBus->dispatch($message);
    }
}
