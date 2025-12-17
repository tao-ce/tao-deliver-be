<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command;

use League\Flysystem\FilesystemReader;
use League\Flysystem\FilesystemWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'user-uploaded-files:move',
)]
class CopyUserUploadedFileMigrationCommand extends Command
{
    public function __construct(
        private readonly FilesystemWriter $deliveryStorage,
        private readonly FilesystemReader $assetStorage,
        private readonly LoggerInterface $logger,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('A command to copy user uploaded files to delivery-execution-uploads folder')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'It defines a limitation for the moving user-uploaded files',
            )->addOption(
                'wet-run',
                null,
                InputOption::VALUE_NONE,
                'Run with applying changes',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Start moving user uploaded files');

        $contentToMove = array_filter($this->assetStorage->listContents('/')->toArray(), function ($item) {
            if ($item['type'] === 'file') {
                return true;
            }
            if ($item['type'] === 'dir' && $this->isFolderContainsUserUploadFile($item['basename'])) {
                return true;
            }
            return false;
        });

        $contentToMove = array_values($contentToMove);

        $limit = $input->getOption('limit') ?? count($contentToMove);
        $applyChanges = $input->getOption('wet-run');
        for ($i = 0; $i < $limit; $i++) {
            $this->copyAssetsFilesAndStructureToDelivery($contentToMove[$i], $applyChanges);
        }

        $io->success('User uploaded files have been moved');

        return Command::SUCCESS;
    }

    private function isFolderContainsUserUploadFile(string $folderName): bool
    {
        return substr_count($folderName, '#') === 3;
    }

    private function copyAssetsFilesAndStructureToDelivery(array $rootItem, bool $applyChanges): void
    {
        if ($rootItem['type'] === 'file') {
            $this->logger->info(sprintf('Copying user uploaded file %s from assets dir', $rootItem['path']));
            if ($applyChanges) {
                $file = $this->assetStorage->read($rootItem['path']);
                $this->deliveryStorage->write($rootItem['path'], $file);
            }
        } elseif ($rootItem['type'] === 'dir') {
            $this->logger->info('Creating directory in delivery-execution-uploads folder ' . $rootItem['path']);
            if ($applyChanges) {
                $this->deliveryStorage->createDirectory($rootItem['path']);
            }
            foreach ($this->assetStorage->listContents($rootItem['path']) as $item) {
                $this->copyAssetsFilesAndStructureToDelivery($item, $applyChanges);
            }
        }
    }
}
