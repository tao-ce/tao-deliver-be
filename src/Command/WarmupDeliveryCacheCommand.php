<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command;

use App\Cache\CacheTrait;
use App\Domain\Delivery\Model\Delivery;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Repository\DeliveryRepository;
use App\Traits\FilesystemTrait;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentTest;
use qtism\data\storage\xml\XmlCompactDocument;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'delivery:cache:warmup',
)]
class WarmupDeliveryCacheCommand extends Command
{
    use CacheTrait;
    use FilesystemTrait;

    private const ARG_DELIVERY_ID = 'deliveryId';
    private const OPT_TTL = 'ttl';

    public function __construct(
        private readonly FilesystemReader $storage,
        private readonly DeliveryRepository $deliveryRepository,
        private readonly LockFactory $lockFactory,
        protected CacheInterface $cache,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Warms up the cache for a given delivery')
            ->addArgument(
                self::ARG_DELIVERY_ID,
                InputArgument::REQUIRED,
                'The ID of the delivery',
            )
            ->addOption(
                self::OPT_TTL,
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Cache TTL',
                default: TestSessionAccessorFactory::CACHE_DEFAULT_TTL,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('warmup');
        $deliveryId = $input->getArgument(self::ARG_DELIVERY_ID);
        $ttl = (int)$input->getOption(self::OPT_TTL);

        $lock = $this->lockFactory->createLock('locks:delivery-cache-warmup:' . $deliveryId);

        if (!$lock->acquire()) {
            $io->warning('The command is already running in locked mode.');
            return Command::FAILURE;
        }

        try {
            $io->info(sprintf('Searching for delivery with ID: %s...', $deliveryId));
            $delivery = $this->deliveryRepository->find($deliveryId);

            $supportedLocales = $delivery->getSupportedLocales();

            if (count($supportedLocales) === 0) {
                $supportedLocales[] = $delivery->getMainLocale();
            }

            foreach ($supportedLocales as $locale) {
                if ($delivery->getMainLocale() == $locale) {
                    $locale = null;
                }

                $this->warmup($delivery, $io, $locale, $ttl);
            }

            $event = $stopwatch->stop('warmup');
            $io->success('Done');
            $io->listing([
                sprintf('Duration: %d s', $event->getDuration() / 1000),
                sprintf('Memory: %d MB', round($event->getMemory() / 1000 / 1000, 2)),
            ]);
        } finally {
            $lock->release();
        }

        return parent::SUCCESS;
    }

    private function warmup(Delivery $delivery, SymfonyStyle $io, ?string $locale, int $ttl): void
    {
        $compactTestFileName = $this->buildPathFor(
            $delivery->getId(),
            $locale
                ? $this->buildPathFor(Delivery::LOCALE_FOLDER_NAME, $locale)
                : null,
            QtiPackageCompiler::COMPACT_TEST_FILE_NAME,
        );
        $compactTestFileCacheKey = md5($compactTestFileName);

        $io->info(sprintf('Searching for QTI Compact Test File: %s...', $compactTestFileName));
        $qtiCompactTestFile = $this->storage->read($compactTestFileName);

        $io->info('Constructing XmlCompactDocument object in memory...');
        $xmlCompactDocument = new XmlCompactDocument();
        $xmlCompactDocument->loadFromString($qtiCompactTestFile);

        /** @var AssessmentTest $assessmentTest */
        $assessmentTest = $xmlCompactDocument->getDocumentComponent();

        $io->info(sprintf('Saving XmlCompactDocument into cache with key %s...', $compactTestFileCacheKey));
        $this->setInCache(
            $compactTestFileCacheKey,
            $xmlCompactDocument,
            $ttl,
        );

        $io->info('Testing that cached XmlCompactDocument can be retrieved...');
        $xmlCompactDocumentFromCache = $this->getFromCache($compactTestFileCacheKey);
        if (get_class($xmlCompactDocumentFromCache) !== XmlCompactDocument::class) {
            throw new CacheException('Failed to retrieve or unserialize cached XmlCompactDocument');
        }

        $items = $assessmentTest->getComponentsByClassName('assessmentItemRef');

        foreach ($items as $item) {
            $this->warmupItemCache($item, $delivery, $io, $ttl, $locale);
        }
    }

    private function warmupItemCache(
        AssessmentItemRef $item,
        Delivery $delivery,
        SymfonyStyle $io,
        int $ttl,
        ?string $locale = null,
    ) {
        $itemJsonFileName = $this->buildPathFor(
            $delivery->getId(),
            $locale
                ? $this->buildPathFor(Delivery::LOCALE_FOLDER_NAME, $locale)
                : null,
            $item->getIdentifier(),
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
        );

        $portableJsonFileName = $this->buildPathFor(
            $delivery->getId(),
            $locale
                ? $this->buildPathFor(Delivery::LOCALE_FOLDER_NAME, $locale)
                : null,
            $item->getIdentifier(),
            QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
        );

        $io->info(sprintf('Searching for %s...', $itemJsonFileName));
        $itemJsonFile = $this->storage->read($itemJsonFileName);

        $io->info(sprintf('Caching %s...', $itemJsonFileName));
        $this->setInCache(
            md5($itemJsonFileName),
            json_decode($itemJsonFile, true),
            $ttl,
        );

        $io->info(sprintf('Testing that cached %s can be retrieved...', $itemJsonFileName));

        $io->info(sprintf('Searching for %s', $portableJsonFileName));
        $portableJsonFile = $this->storage->read($portableJsonFileName);

        $io->info(sprintf('Caching %s...', $portableJsonFileName));
        $this->setInCache(
            md5($portableJsonFileName),
            json_decode($portableJsonFile, true),
            $ttl,
        );

        $io->info(sprintf('Testing that cached %s can be retrieved...', $portableJsonFileName));
    }
}
