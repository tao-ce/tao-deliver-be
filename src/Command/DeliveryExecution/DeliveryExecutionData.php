<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryRepository;
use App\Serializer\Normalizer\DeliveryExecutionNormalizer;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use Google\Auth\Cache\InvalidArgumentException;
use qtism\runtime\common\Variable;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use qtism\runtime\tests\RouteItem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ZipArchive;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'delivery-execution:data',
)]
class DeliveryExecutionData extends Command
{
    public function __construct(
        private readonly RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly DeliveryExecutionNormalizer $deliveryExecutionNormalizer,
        private readonly DeliveryRepository $deliveryRepository,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly ZipArchive $zipArchive,
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
                'id',
                InputArgument::REQUIRED,
                'deliveryExecutionId',
            )
            ->addOption(
                'delivery',
                null,
                InputOption::VALUE_NONE,
                'Include delivery data to response',
            )
            ->addOption(
                'with-qti-compiled-delivery',
                null,
                InputOption::VALUE_NONE,
                'Add base64 of compiled data for delivery; should be used only with --delivery option',
            )
            ->addOption(
                'qti-test',
                null,
                InputOption::VALUE_NONE,
                'Return data for qti test and related session',
            )
            ->addOption(
                'base64',
                null,
                InputOption::VALUE_NONE,
                'base64 encoded output',
            )
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'Make JSON output pretty',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $deliveryExecutionId = $input->getArgument('id');

        $io->title('Delivery execution Data');
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
        $deliveryExecutionData = $this->deliveryExecutionNormalizer->normalize($deliveryExecution);

        if ($input->getOption('delivery') || $input->getOption('with-qti-compiled-delivery')) {
            $this->addDelivery($deliveryExecutionData, $deliveryExecution->getDeliveryId());
        }

        if ($input->getOption('with-qti-compiled-delivery')) {
            $this->addQtiCompiledDelivery(
                $deliveryExecutionData,
                $deliveryExecution->getDeliveryId(),
            );
        }

        if ($input->getOption('qti-test')) {
            $this->addQtiTestData($deliveryExecutionData, $deliveryExecution);
        }

        $deliveryExecutionData = json_encode(
            $deliveryExecutionData,
            JSON_UNESCAPED_UNICODE | ($input->getOption('pretty') ? JSON_PRETTY_PRINT : 0),
        );

        if ($input->getOption('base64')) {
            $deliveryExecutionData = base64_encode($deliveryExecutionData);
        }

        $io->writeln($deliveryExecutionData);
        return parent::SUCCESS;
    }

    private function addQtiCompiledDelivery(&$deliveryExecutionData, string $deliveryId): void
    {
        $qtiCompiledDeliveryDir = $this->qtiCompiledPrefix . DIRECTORY_SEPARATOR . $deliveryId;
        $archiveFile = $qtiCompiledDeliveryDir . '.zip';

        if (true !== $this->zipArchive->open($archiveFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new InvalidArgumentException('Cannot create qti compile files');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($qtiCompiledDeliveryDir),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($files as $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($qtiCompiledDeliveryDir) + 1);

                // Add current file to archive
                $this->zipArchive->addFile($filePath, $relativePath);
            }
        }

        $this->zipArchive->close();

        $deliveryExecutionData['qtiCompiledDelivery'] = base64_encode(file_get_contents($archiveFile));
        unlink($archiveFile);
    }

    private function addDelivery(array &$deliveryExecutionData, string $deliveryId): void
    {
        $delivery = $this->deliveryRepository->find($deliveryId);
        $deliveryExecutionData['delivery'] =  [
            'deliveryId' => $delivery->getId(),
            'tenantId' => $delivery->getTenantId(),
            'configuration' => $delivery->getConfiguration(),
            'compactTestFilePath' => $delivery->getQtiCompactTestFilePath(),
            'qtiItemsMapping' => $delivery->getQtiItemsMapping(),
            'packageRef' => $delivery->getPackageRef(),
            'isDeleted' => $delivery->isDeleted(),
            'draftId' => $delivery->getDraftId(),
        ];
    }

    private function addQtiTestData(array &$deliveryExecutionData, DeliveryExecution $deliveryExecution): void
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $assessmentTest = $testSession->getAssessmentTest();
        $deliveryExecutionData['qtiTest'] =  [
            'id' => $assessmentTest->getIdentifier(),
            'title' => $assessmentTest->getTitle(),
            'toolName' => $assessmentTest->getToolName(),
            'toolVersion' => $assessmentTest->getToolVersion(),
            'timeLimits' => $assessmentTest->getTimeLimits(),
            'qtiClass' => $assessmentTest->getQtiClassName(),
            'hasTimeLimits' => $assessmentTest->hasTimeLimits(),
            'session' => [
                'id' => $testSession->getSessionId(),
                'currentItemId' => $testSession->getCurrentAssessmentItemRef()
                    ? $testSession->getCurrentAssessmentItemRef()?->getIdentifier()
                    : null,
                'state' => AssessmentTestSessionState::getNameByConstant($testSession->getState()),
            ],
            'variables' => $this->getVariables($testSession),
        ];
    }

    private function getVariables(AssessmentTestSession $testSession): array
    {
        $result = [];
        /** @var RouteItem $routeItem */
        foreach ($testSession->getRoute()->getAllRouteItems() as $routeItem) {
            $itemIdentifier = $routeItem->getAssessmentItemRef()->getIdentifier();
            /** @var AssessmentItemSession $itemSession */
            foreach ($testSession->getAssessmentItemSessions($itemIdentifier) as $itemSession) {
                /** @var Variable $variable */
                foreach ($itemSession->getAllVariables() as $variable) {
                    $result[$itemIdentifier][$variable->getIdentifier()] = (string)$variable->getValue();
                }
            }
        }

        return $result;
    }
}
