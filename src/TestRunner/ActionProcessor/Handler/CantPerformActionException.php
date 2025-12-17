<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ActionProcessor\Exception\ConflictException;

class CantPerformActionException extends ConflictException
{
    private const CODE_TERMINATED = 100;
    private const CODE_CLOSED = 101;
    private const CODE_SUSPENDED = 102;
    private const CODE_UNAVAILABLE_STATUS = 104;
    private const CODE_RESET = 105;

    private const MESSAGE_TEMPLATE = 'Can\'t perform the action "%s" because the test session is %s';
    private const MESSAGE_UNAVAILABLE_STATUS_TEMPLATE = 'Can\'t perform the action "%s" because the test session in unavailable status "%s"';

    public static function becauseStatus(string $action, string $status): self
    {
        return match ($status) {
            DeliveryExecution::STATUS_TERMINATED => CantPerformActionException::becauseTestSessionIsTerminated(
                $action,
            ),
            DeliveryExecution::STATUS_SUSPENDED => CantPerformActionException::becauseTestSessionIsSuspended(
                $action,
            ),
            DeliveryExecution::STATUS_CLOSED => CantPerformActionException::becauseTestSessionIsClosed($action),
            default => CantPerformActionException::becauseUnavailableStatus($action, $status),
        };
    }

    public static function becauseTestSessionIsClosed(string $action): self
    {
        return new self(
            sprintf(self::MESSAGE_TEMPLATE, $action, 'closed'),
            self::CODE_CLOSED,
        );
    }

    public static function becauseTestSessionIsTerminated(string $action): self
    {
        return new self(
            sprintf(self::MESSAGE_TEMPLATE, $action, 'terminated'),
            self::CODE_TERMINATED,
        );
    }

    public static function becauseTestSessionIsSuspended(string $action): self
    {
        return new self(
            sprintf(self::MESSAGE_TEMPLATE, $action, 'suspended'),
            self::CODE_SUSPENDED,
        );
    }

    public static function becauseUnavailableStatus(string $action, string $status): self
    {
        return new self(
            sprintf(self::MESSAGE_UNAVAILABLE_STATUS_TEMPLATE, $action, $status),
            self::CODE_UNAVAILABLE_STATUS,
        );
    }

    public static function becauseTestSessionReset(string $action): self
    {
        return new self(
            sprintf(self::MESSAGE_TEMPLATE, $action, 'reset'),
            self::CODE_RESET,
        );
    }
}
