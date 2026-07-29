<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Infrastructure;

use App\Service\Infrastructure\Contract\MemoizedService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

class MemoizationService
{
    /**
     * @param iterable<MemoizedService> $memoizedServices
     */
    public function __construct(private iterable $memoizedServices)
    {
    }

    #[AsEventListener(event: WorkerMessageHandledEvent::class)]
    #[AsEventListener(event: WorkerMessageFailedEvent::class)]
    public function flushAll(): void
    {
        foreach ($this->memoizedServices as $memoizedService) {
            $memoizedService->flush();
        }
    }
}
