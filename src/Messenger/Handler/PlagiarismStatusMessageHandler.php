<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use App\Messenger\Message\PlagiarismStatusMessage;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use Carbon\Carbon;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PlagiarismStatusMessageHandler
{
    private const LOCK_KEY_PATTERN = 'locks:plagiarism-report-update-%s';

    public function __construct(
        private LockFactory $lockFactory,
        private float $lockTtl,
        private RepositoryAwareDeliveryExecutionServiceInterface $loggerAwareDeliveryExecutionService,
    ) {
    }

    public function __invoke(PlagiarismStatusMessage $message): void
    {
        $lock = $this->acquireLock($message);

        $deliveryExecution = $this->findDeliveryExecution($message);
        if ($deliveryExecution) {
            $deliveryExecution->addPlagiarismReport($this->makePlagiarismReport($message));
            $this->loggerAwareDeliveryExecutionService->saveDeliveryExecution($deliveryExecution);
        }

        $lock->release();
    }

    private function acquireLock(PlagiarismStatusMessage $message): LockInterface
    {
        $lock = $this->lockFactory->createLock(
            sprintf(self::LOCK_KEY_PATTERN, $message->getAssessmentId()),
            $this->lockTtl,
        );
        $lock->acquire(true);

        return $lock;
    }

    private function findDeliveryExecution(PlagiarismStatusMessage $message): ?DeliveryExecution
    {
        return $this->loggerAwareDeliveryExecutionService->findDeliveryExecution($message->getAssessmentId());
    }

    private function makePlagiarismReport(PlagiarismStatusMessage $message)
    {
        return new PlagiarismReport(
            $message->getProvider(),
            $message->getId(),
            Carbon::parse($message->getCreatedAt()),
            $message->getItemId(),
            $message->getResponseId(),
            $message->getStatus(),
            $message->getHref(),
        );
    }
}
