<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Request\Extractor;

use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionKeyInfo;
use App\Repository\DeliveryExecutionRepository;
use App\Request\Domain\Context;
use App\Request\Extractor\Contract\ContextExtractorInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class RequestContextExtractor implements ContextExtractorInterface
{
    private const DELIVERY_EXECUTION_ID_MATCH_GROUP = 'deliveryExecutionId';
    private const PATH_PATTERN = '~/delivery-executions/(?<' . self::DELIVERY_EXECUTION_ID_MATCH_GROUP . '>[^/]+)~';

    private array $matches = [];
    private ?string $id = null;
    private ?Context $context = null;

    public function __construct(private readonly DeliveryExecutionRepository $repository)
    {
    }

    public function supports(Request $request): bool
    {
        return (bool)preg_match(self::PATH_PATTERN, $request->getPathInfo(), $this->matches);
    }

    public function extract(): Context
    {
        if ($this->context === null) {
            $deliveryExecutionKeyInfo = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo(
                $this->getDeliveryExecutionId(),
            );

            $this->context = null === $deliveryExecutionKeyInfo
                ? new Context()
                : new Context(
                    $deliveryExecutionKeyInfo->isReview(),
                    $deliveryExecutionKeyInfo->getTenantId(),
                    $deliveryExecutionKeyInfo->getDeliveryId(),
                    $deliveryExecutionKeyInfo->getUserId(),
                    $this->fetchBatteryId($deliveryExecutionKeyInfo),
                );
        }

        return $this->context;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    private function getDeliveryExecutionId(): string
    {
        return $this->id ?? urldecode($this->matches[self::DELIVERY_EXECUTION_ID_MATCH_GROUP]);
    }

    private function fetchBatteryId(DeliveryExecutionKeyInfo $keyInfo): ?string
    {
        try {
            return $this->repository->find((string)$keyInfo)?->getBatteryId();
        } catch (DocumentNotFoundException) {
            return null;
        }
    }
}
