<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Cache\CacheTrait;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionKeyInfo;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use Exception;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use Psr\Cache\CacheException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;

class GetItemDataService
{
    use CacheTrait;
    use FilesystemTrait;

    public function __construct(
        private readonly FilesystemReader $qtiCompiledDeliveriesStorage,
        protected CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function getItemDataByDelivery(string $itemIdentifier, Delivery $delivery, ?string $locale): array
    {
        return $this->getItemDataByDeliveryExecution(
            $itemIdentifier,
            new DeliveryExecution(
                (string)new DeliveryExecutionKeyInfo(
                    DeliveryExecution::REVIEW_MODE_PREFIX,
                    strrev('anonymous'),
                    $delivery->getId(),
                    sha1(DeliveryExecution::DRY_RUN_ATTEMPT_ID),
                    $delivery->getTenantId(),
                ),
                $delivery->getId(),
                $delivery->getTenantId(),
                Carbon::now(),
                ['result_id' => DeliveryExecution::ATTEMPT_ID],
                null,
                locale: $delivery->getMainLocale() === $locale ? null : $locale,
            ),
        );
    }

    public function getItemDataByDeliveryExecution(string $itemIdentifier, DeliveryExecution $deliveryExecution): array
    {
        $locale = $deliveryExecution->getLocale();
        $cacheKey = $this->getCacheKey(
            $deliveryExecution->getDeliveryId(),
            $itemIdentifier,
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
            $locale,
        );

        try {
            $itemData = $this->getFromCache($cacheKey);

            if (null !== $itemData) {
                $this->auditDeliveryExecutionLogger->debug(
                    sprintf(
                        '[%s][GetItemDataService] - got item data %s from the cache',
                        $deliveryExecution->getId(),
                        $itemIdentifier,
                    ),
                );
            }
        } catch (CacheException $exception) {
            $itemData = null;
            $this->logger->error($exception->getMessage(), compact('exception'));
        }

        if (null === $itemData) {
            $itemData = $this->getItemData($deliveryExecution, $itemIdentifier);

            $this->auditDeliveryExecutionLogger->debug(
                sprintf(
                    '[%s][GetItemDataService] - got item data %s from the compiled delivery storage',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );

            try {
                $this->setInCache(
                    $cacheKey,
                    $itemData,
                    TestSessionAccessorFactory::CACHE_DEFAULT_TTL,
                );

                $this->auditDeliveryExecutionLogger->debug(
                    sprintf(
                        '[%s][GetItemDataService] - put item data %s in the cache',
                        $deliveryExecution->getId(),
                        $itemIdentifier,
                    ),
                );
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage(), compact('exception'));
            }
        }

        return $itemData;
    }

    private function getItemData(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        try {
            $path = $deliveryExecution->getItemDataPath($itemIdentifier);
            $itemDataJsonEncoded = $this->qtiCompiledDeliveriesStorage->read($path);

            return json_decode($itemDataJsonEncoded, true);
        } catch (UnableToReadFile $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }
}
