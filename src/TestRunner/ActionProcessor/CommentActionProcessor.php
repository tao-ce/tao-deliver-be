<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use Psr\Log\LoggerInterface;

class CommentActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'comment';

    /** @var LoggerInterface */
    private $auditDeliveryExecutionLogger;

    public function __construct(LoggerInterface $auditDeliveryExecutionLogger)
    {
        $this->auditDeliveryExecutionLogger = $auditDeliveryExecutionLogger;
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $deliveryExecution->addItemComment(
            $actionParameters['parameters']['itemIdentifier'],
            $actionParameters['parameters']['comment'],
        );

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has added a comment for the following Item: [%s]',
                $deliveryExecution->getId(),
                $actionParameters['parameters']['itemIdentifier'],
            ),
        );

        return $this->getActionProcessorResponse($actionParameters, []);
    }
}
