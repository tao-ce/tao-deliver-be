<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Stubs;

use OAT\Library\TaoTimerClient\Client\{
    DeleteTimerException,
    GetTimerException,
    StartTimerException,
    StopTimerException
};
use OAT\Library\TaoTimerClient\Model\Contract\{InboundMsgInterface, TimerDefinitionInterface};
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;

class StubTimerService implements TimerServiceInterface
{
    /** @var TimerDefinitionInterface[] */
    private $timerList = [];

    public function createTimer(string $deliveryExecutionId, TimerDefinitionInterface $timerDefinition): void
    {
        $this->timerList[$deliveryExecutionId] = $timerDefinition;
    }

    /**
     * @throws GetTimerException
     */
    public function getTimer(string $deliveryExecutionId): TimerDefinitionInterface
    {
        if (empty($this->timerList[$deliveryExecutionId])) {
            throw new GetTimerException('no timer found');
        }

        return $this->timerList[$deliveryExecutionId];
    }

    /**
     * @throws DeleteTimerException
     */
    public function deleteTimer(string $deliveryExecutionId): void
    {
        if (empty($this->timerList[$deliveryExecutionId])) {
            throw new DeleteTimerException('no timer found');
        }
        unset($this->timerList[$deliveryExecutionId]);
    }

    /**
     * @throws StopTimerException
     */
    public function stopTimer(string $deliveryExecutionId): void
    {
        if (empty($this->timerList[$deliveryExecutionId])) {
            throw new StopTimerException('no timer found');
        }
    }

    /**
     * @throws StartTimerException
     */
    public function startTimer(string $deliveryExecutionId, InboundMsgInterface $inboundMsg): void
    {
        if (empty($this->timerList[$deliveryExecutionId])) {
            throw new StartTimerException('no timer found');
        }
    }
}
