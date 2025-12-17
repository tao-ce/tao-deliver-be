<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository\DeliveryExecutionAlias;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionAlias;
use App\Repository\DeliveryExecutionAlias\Contract\DeliveryExecutionIdentifierAliasRepositoryInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;
use InvalidArgumentException;

class DeliveryExecutionIdentifierAliasRepository extends DocumentRepository implements
    DeliveryExecutionIdentifierAliasRepositoryInterface
{
    public function __construct(
        DocumentManagerInterface $manager,
    ) {
        parent::__construct($manager, DeliveryExecutionAlias::class);
    }


    public function findDeliveryExecutionId(string $tenantId, string $alias): ?string
    {
        try {
            /** @var DeliveryExecutionAlias $deliveryExecutionAlias */
            $deliveryExecutionAlias = $this->find($this->createDocumentId($tenantId, $alias));
        } catch (DocumentNotFoundException) {
            return null;
        }

        return $deliveryExecutionAlias->getDeliveryExecutionId();
    }

    public function saveDeliveryExecutionId(string $tenantId, string $alias, string $deliveryExecutionId): DeliveryExecutionAlias
    {
        $recordDeliveryExecutionId = $this->findDeliveryExecutionId($tenantId, $alias);
        if (
            null !== $recordDeliveryExecutionId
            && $recordDeliveryExecutionId !== $deliveryExecutionId
        ) {
            throw new InvalidArgumentException(sprintf(
                'More then 1 alias `%s` was found for tenant `%s`',
                $alias,
                $tenantId,
            ));
        }
        $this->save(new DeliveryExecutionAlias($this->createDocumentId($tenantId, $alias), $deliveryExecutionId));

        return new DeliveryExecutionAlias($this->createDocumentId($tenantId, $alias), $deliveryExecutionId);
    }

    public function deleteDeliveryExecutionId(string $aliasId): void
    {
        $this->delete(new DeliveryExecutionAlias($aliasId, null));
    }

    private function createDocumentId(string $tenantId, string $alias): string
    {
        return sprintf('%s#%s', $alias, $tenantId);
    }
}
