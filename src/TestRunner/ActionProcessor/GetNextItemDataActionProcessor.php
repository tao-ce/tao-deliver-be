<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\Service\GetItemService;
use Psr\Log\LoggerInterface;

class GetNextItemDataActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'getNextItemData';

    /** @var GetItemService */
    private $getItemService;

    /** @var LoggerInterface */
    private $auditDeliveryExecutionLogger;

    public function __construct(GetItemService $getItemService, LoggerInterface $auditDeliveryExecutionLogger)
    {
        $this->getItemService = $getItemService;
        $this->auditDeliveryExecutionLogger = $auditDeliveryExecutionLogger;
    }

    public function getActionName(): string
    {
        return static::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $items = [];
        $itemIdentifiers = $actionParameters['parameters']['itemIdentifier'];

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has requested the following next Item[s]: %s',
                $deliveryExecution->getId(),
                json_encode($itemIdentifiers),
            ),
        );

        foreach ($itemIdentifiers as $itemIdentifier) {
            $items[] = $this->getItemService->getItem($deliveryExecution, $itemIdentifier);
        }

        return $this->getActionProcessorResponse($actionParameters, ['items' => $items]);
    }
}
