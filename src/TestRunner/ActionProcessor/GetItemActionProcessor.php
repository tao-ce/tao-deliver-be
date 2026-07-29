<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\Service\GetItemService;
use Psr\Log\LoggerInterface;

class GetItemActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'getItem';

    /** @var GetItemService */
    private $getItemService;

    /** @var LoggerInterface */
    private $auditDeliveryExecutionLogger;

    public function __construct(
        GetItemService $getItemService,
        LoggerInterface $auditDeliveryExecutionLogger,
    ) {
        $this->getItemService = $getItemService;
        $this->auditDeliveryExecutionLogger = $auditDeliveryExecutionLogger;
    }

    public function getActionName(): string
    {
        return static::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $itemIdentifier  = $actionParameters['parameters']['itemIdentifier'];
        $requestDataType = $actionParameters['requestDataType'] ?? GetItemService::DATA_TYPE_BOTH;

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has requested the following Item: %s',
                $deliveryExecution->getId(),
                $itemIdentifier,
            ),
        );

        switch (true) {
            case $requestDataType & GetItemService::DATA_TYPE_DYNAMIC
                && $requestDataType & GetItemService::DATA_TYPE_STATIC:
                $responseParams = $this->getItemService->getItem($deliveryExecution, $itemIdentifier);
                break;
            case $requestDataType & GetItemService::DATA_TYPE_DYNAMIC:
                $responseParams = $this->getItemService->getItemDynamicData($deliveryExecution, $itemIdentifier);
                break;
            case $requestDataType & GetItemService::DATA_TYPE_STATIC:
                $responseParams = $this->getItemService->getItemStaticData($deliveryExecution, $itemIdentifier);
                break;
        }

        return $this->getActionProcessorResponse(
            $actionParameters,
            $responseParams,
        );
    }
}
