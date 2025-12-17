<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use Monolog\Logger;

trait LtiLaunchLogTestingTrait
{
    use LoggerTestingTrait;

    private function assertLaunchLtiActionLogs(array $parameters, ?string $resultId = null): void
    {
        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - test was initialized',
                $parameters['deliveryExecutionId'],
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );

        if ($resultId) {
            $this->assertHasLogRecordWithMessage(
                sprintf(
                    '[%s] - result identifier: [%s] was provided for current test',
                    $parameters['deliveryExecutionId'],
                    $resultId,
                ),
                Logger::INFO,
                'audit_delivery_execution',
            );
        }

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - test taker will be redirected to the graphical user interface (FE): %s ',
                $parameters['deliveryExecutionId'],
                static::getContainer()->getParameter('deliver.frontend.url'),
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );
    }
}
