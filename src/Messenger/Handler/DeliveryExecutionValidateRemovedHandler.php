<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Messenger\Message\DataPolicy\ConfirmationMessage;
use App\Messenger\Message\DataPolicy\ValidationRequestMessage;
use App\Messenger\Message\DataPolicy\ValidationConfirmationMessage;
use App\Repository\DeliveryExecutionRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class DeliveryExecutionValidateRemovedHandler
{
    private const TYPE_TO_STATUS = [
        'remove-delivery-execution-data-submitted' => [
            DeliveryExecutionStatus::STATUS_CLOSED,
        ],
        'remove-test-activities-for-submitted-enrollment' => [
            DeliveryExecutionStatus::STATUS_CLOSED,
        ],
        'remove-test-activities-for-non-submitted-enrollment' => [
            DeliveryExecutionStatus::STATUS_INITIAL,
            DeliveryExecutionStatus::STATUS_INTERACTING,
            DeliveryExecutionStatus::STATUS_TERMINATED,
        ],
        'remove-delivery-execution-data-non-submitted' => [
            DeliveryExecutionStatus::STATUS_INITIAL,
            DeliveryExecutionStatus::STATUS_INTERACTING,
            DeliveryExecutionStatus::STATUS_TERMINATED,
        ],
        'remove-candidate-delivery-execution' => [
            DeliveryExecutionStatus::STATUS_CLOSED,
            DeliveryExecutionStatus::STATUS_INITIAL,
            DeliveryExecutionStatus::STATUS_INTERACTING,
            DeliveryExecutionStatus::STATUS_TERMINATED,
        ],
    ];
    public function __construct(
        private DeliveryExecutionRepository $repository,
        private LoggerInterface $auditPlatformLogger,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function __invoke(ValidationRequestMessage $message): void
    {
        //
        // at queue we have only process messages from our app
        //
        if ($message->ownerApp !== ConfirmationMessage::DEFAULT_OWNER_APP) {
            return;
        }

        if (empty(self::TYPE_TO_STATUS[$message->policyId])) {
            throw new RuntimeException(sprintf('Incorrect policyId for TYPE_TO_STATUS mapping: %s', $message->policyId));
        }

        try {
            $result = $this->repository->existsForUserIdAndStatuses(
                $message->userId,
                self::TYPE_TO_STATUS[$message->policyId],
            );

            //
            // if no records is exist by condition
            // we are good and could to send success event
            // if we have some no events should be exposed at all
            //
            if (!$result) {
                $this->messageBus->dispatch(
                    ValidationConfirmationMessage::createRemovalConfirmationMessage($message),
                );
            }
        } catch (RuntimeException $e) {
            $this->auditPlatformLogger->error(sprintf(
                'Failed to validate deleted deliveryExecutions for message type %s and userId %s; Error: %s',
                $message->type,
                $message->userId,
                $e->getMessage(),
            ));
        }
    }
}
