<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\DeliveryExecution;

use App\Service\AssessmentControl\AssessmentControlProcessor;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use Carbon\Carbon;
use OAT\Library\Lti1p3Proctoring\Factory\AcsControlFactory;
use OAT\Library\Lti1p3Proctoring\Factory\AcsControlFactoryInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'delivery-execution:assessment-control',
    description: 'Control the assessment execution ACS-style',
)]
final class AssessmentControl extends Command
{
    private AcsControlFactoryInterface $acsControlFactory;
    private array $ids = [];
    private string $action;
    private ?string $extraMinutes;
    private SymfonyStyle $io;

    public function __construct(
        private readonly DeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly AssessmentControlProcessor $assessmentControlProcessor,
        ?AcsControlFactoryInterface $acsControlFactory = null,
    ) {
        parent::__construct();
        $this->acsControlFactory = $acsControlFactory ?? new AcsControlFactory();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'The delivery execution identifier or a file containing a list of identifiers',
            )
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'The action to perform, available values: ' . implode(', ', AcsControlInterface::SUPPORTED_ACTIONS),
            )
            ->addOption(
                'extra-minutes',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Extra time (in minutes) to add to the delivery execution',
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $id = $input->getArgument('id');
        $this->initializeIds($id);
        $this->action = $input->getArgument('action');
        $this->extraMinutes = $input->getOption('extra-minutes');
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io->progressStart(count($this->ids));
        foreach ($this->ids as $id) {
            $this->executeForId($id);
            $this->io->progressAdvance();
        }

        return Command::SUCCESS;
    }

    private function initializeIds(string $id): void
    {
        if (substr_count($id, '#') === 3) {
            $this->ids = [$id];
            return;
        }
        $handle = fopen($id, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open file $id");
        }
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line) {
                $this->ids[] = $line;
            }
        }
        fclose($handle);
    }

    private function executeForId(string $id): void
    {
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($id);
        $acsControl = $this->acsControlFactory->create(
            [
                'resource_link' => [
                    'id' => $deliveryExecution->getResourceLink()->getIdentifier(),
                ],
                'user' => [
                    'sub' => $deliveryExecution->getUserId(),
                    'iss' => '',
                ],
                'action' => $this->action,
                'attempt_number' => $deliveryExecution->getAttempt(),
                'incident_time' => Carbon::now(),
                'extra_time' => $this->extraMinutes,
                'reason_code' => '999',
                'reason_msg' => "Manual $this->action",
            ],
        );

        $this->io->writeln(
            json_encode(($this->assessmentControlProcessor)($deliveryExecution, $acsControl)),
        );
    }
}
