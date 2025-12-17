<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command;

use App\Domain\Publication\Model\Publication;
use App\Service\Publication\CreatePublicationService;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'publication:run',
)]
class PublicationCommand extends Command
{
    public function __construct(
        private LoggerInterface $logger,
        private CreatePublicationService $createPublicationService,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('A command to manually publish tests')
            ->addArgument('testPath', InputArgument::REQUIRED, 'Path to the ZIP package archive or to the base64 encoded if `--base64` flag is provided')
            ->addArgument(
                'tenantId',
                InputArgument::REQUIRED,
                'Tenant ID',
            )
            ->addArgument(
                'deliveryId',
                InputArgument::OPTIONAL,
                'Delivery ID to force',
            )
            ->addOption(
                'configuration',
                'c',
                InputOption::VALUE_OPTIONAL,
                'JSON configuration of the test',
                '{}',
            )
            ->addOption(
                'base64',
                null,
                InputOption::VALUE_NONE,
                'base64 encoded content provided',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test publication');

        $filePath = $input->getArgument('testPath');
        $package = file_get_contents($filePath);
        if (false === $package) {
            throw new RuntimeException(sprintf('%s is not readable', $filePath));
        }

        if (!$input->getOption('base64')) {
            $package = base64_encode($package);
        }

        $configuration = $this->getConfiguration($input->getOption('configuration'));
        $tenantId = $input->getArgument('tenantId');

        $publication = $this->createPublicationService->create(
            $package,
            '',
            $tenantId,
            $configuration,
            $input->getArgument('deliveryId'),
        );

        if ($publication->getStatus() === Publication::STATUS_CREATED) {
            $message = sprintf('Publication %s successfully created', $publication->getId());
            $io->success($message);
            $this->logger->info($message);
            return parent::SUCCESS;
        }

        return parent::FAILURE;
    }

    /**
     * @throws JsonException
     */
    private function getConfiguration(string $jsonConfiguration): array
    {
        $configuration = json_decode(
            $jsonConfiguration,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $allowedKeys = [
            'label',
            'status',
            'metadata',
            'availabilityDate',
            'expiryDate',
        ];

        $forbiddenKeys = array_diff(array_keys($configuration), $allowedKeys);
        if ($forbiddenKeys !== []) {
            throw new InvalidParameterException(sprintf(
                'The JSON configuration use forbidden key(s): %s',
                implode(', ', $forbiddenKeys),
            ));
        }

        return $configuration;
    }
}
