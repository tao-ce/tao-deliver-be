<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Service\TestSessionInitiator;
use App\TestRunner\Service\TestSessionNavigator;
use Exception;
use OutOfBoundsException;
use Psr\EventDispatcher\EventDispatcherInterface;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\MultipleContainer;
use qtism\runtime\common\OrderedContainer;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\pci\json\Unmarshaller;
use qtism\runtime\processing\OutcomeProcessingEngine;
use qtism\runtime\processing\ResponseProcessingEngine;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'delivery-execution:manipulate-state',
)]
class ManipulateState extends Command
{
    private SymfonyStyle $io;
    private DeliveryExecution $deliveryExecution;
    private AssessmentTestSession $session;
    private StateManipulationMode $mode;
    private ?array $state;
    private ?string $itemId;

    public function __construct(
        private readonly DeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly TestSessionInitiator $testSessionInitiator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Unmarshaller $qtiJsonUnmarshaller,
        private readonly TestSessionNavigator $testSessionNavigator,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This allows modifying the item state of the given delivery execution')
            ->addArgument(
                'deliveryExecutionId',
                InputArgument::REQUIRED,
                'ID of the delivery execution record to manipulate the item state of',
            )
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_REQUIRED,
                'Item state manipulation mode; possible values: ' . implode(
                    ', ',
                    array_column(StateManipulationMode::cases(), 'value'),
                ),
                StateManipulationMode::READ->value,
            )
            ->addOption(
                'state',
                's',
                InputOption::VALUE_REQUIRED,
                'JSON-encoded Item State to apply',
                '{}',
            )
            ->addOption(
                'rewindToItemId',
                'i',
                InputOption::VALUE_REQUIRED,
                'Item ID to rewind the session to; this will also cause the item state to be applied as QTI response',
            )
            ->addOption(
                'end',
                mode: InputOption::VALUE_NONE,
                description: 'Whether to submit the assessment and send the results to Data Store',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io = new SymfonyStyle($input, $output);
        $this->mode = StateManipulationMode::from($input->getOption('mode'));
        $this->deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail(
            $input->getArgument('deliveryExecutionId'),
        );
        if ($this->mode->isClearing()) {
            $this->deliveryExecution->setQtiSdkEncodedTestSession(null);
        }
        $this->session = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution, true);
        if ($this->session->getState() === AssessmentTestSessionState::INITIAL) {
            $this->testSessionInitiator->startQtiSession($this->deliveryExecution);
        }
        $this->state = match (true) {
            $this->mode->isPurging() => [],
            $this->mode->isReadonly() => null,
            default => json_decode($input->getOption('state'), true, 512, JSON_THROW_ON_ERROR),
        };
        $this->itemId = $input->getOption('rewindToItemId');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isSubmission = $input->getOption('end');
        if (null !== $this->state) {
            $this->io->writeln(
                "You are about to {$this->mode->value} item state of Delivery Execution {$this->deliveryExecution->getId()}.",
            );
        }
        $this->io->title('Current');
        try {
            $routeItem = $this->session->getRoute()->current();
        } catch (OutOfBoundsException) {
            $routeItem = $this->session->getRoute()->getLastRouteItem();
        }
        $this->printItemId($routeItem->getAssessmentItemRef()->getIdentifier());
        $this->printState();
        if (null === $this->state) {
            return parent::SUCCESS;
        }
        $this->io->title('New');

        if ($this->mode->isClearing()) {
            $this->deliveryExecution->clearAllItemState();
        }
        foreach ($this->state as $id => $itemState) {
            $this->deliveryExecution->addItemState($id, json_encode($itemState));
            $this->applyItemStateToSession($id, $itemState);
        }
        if ($this->itemId === 'last') {
            $this->itemId = array_key_last($this->deliveryExecution->getExtraStateData()->getItemStates());
        }
        $this->printItemId($this->itemId);
        if (null !== $this->itemId) {
            $this->testSessionNavigator->navigateToItemRef($this->deliveryExecution, $this->itemId);
        }
        $this->printState();
        $this->processTestOutcome();
        $this->deliveryExecutionPropertyService->persistTestSession($this->session);
        if ($isSubmission && !$this->deliveryExecution->isStateFinal()) {
            $this->deliveryExecution->close();
            $this->eventDispatcher->dispatch(new DeliveryExecutionClosedEvent($this->deliveryExecution));
        }
        if (!$this->io->confirm('Continue?', false)) {
            return parent::INVALID;
        }
        $this->deliveryExecutionService->saveDeliveryExecution($this->deliveryExecution);
        if ($isSubmission) {
            $this->eventDispatcher->dispatch(
                new TestSessionEndEvent(self::class, $this->deliveryExecution),
            );
        }
        return parent::SUCCESS;
    }

    private function applyItemStateToSession(string $itemId, array $itemState): void
    {
        foreach ($itemState as $variableId => $variableState) {
            if (!isset($variableState['response'])) {
                continue;
            }

            $itemSessions = $this->session->getAssessmentItemSessions($itemId)->getArrayCopy();
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
            try {
                $sessionVariable->setValue($value);
                $rule = $itemSession->getAssessmentItem()->getResponseProcessing();
                if (
                    $rule !== null
                    && (
                        $rule->hasTemplate()
                        || $rule->hasTemplateLocation()
                        || !$rule->getResponseRules()->isEmpty()
                    )
                ) {
                    (new ResponseProcessingEngine($rule, $itemSession))->process();
                }
                $itemSession['completionStatus']->setValue(AssessmentItemSession::COMPLETION_STATUS_COMPLETED);
                $itemSession['numAttempts']->setValue(1);
            } catch (Exception $exception) {
                $this->io->warning($exception->getMessage());
            }
        }
    }

    private function processTestOutcome(): void
    {
        if (!$this->session->getAssessmentTest()->hasOutcomeProcessing()) {
            return;
        }

        $this->session->resetOutcomeVariables();
        $outcomeProcessing = $this->session->getAssessmentTest()->getOutcomeProcessing();

        $outcomeProcessingEngine = new OutcomeProcessingEngine($outcomeProcessing, $this->session);
        $outcomeProcessingEngine->process();
    }

    private function printItemId(?string $itemId): void
    {
        if ($itemId === null) {
            return;
        }
        $this->io->section('Item');
        $this->io->writeln($itemId);
    }

    private function printState(): void
    {
        $this->io->section('State');
        $this->io->writeln(
            json_encode(
                array_map('json_decode', $this->deliveryExecution->getExtraStateData()->getItemStates()),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ),
        );
    }
}
