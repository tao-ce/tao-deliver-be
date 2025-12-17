<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\AssessmentControl;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\Event\AcsControlProcessedEvent;
use App\Lti\Exception\LtiAcsActionProcessorException;
use App\Lti\Proctoring\AcsActionProcessor\AcsActionProcessorInterface;
use App\Service\AssessmentControl\Exception\NotControllableDeliveryExecutionException;
use App\Service\AssessmentControl\Exception\NotSupportedAssessmentControlAction;
use Exception;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class AssessmentControlProcessor
{
    public function __construct(
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly iterable $acsActionProcessors = [],
    ) {
    }

    /**
     * @throws NotSupportedAssessmentControlAction
     * @throws NotControllableDeliveryExecutionException
     * @throws LtiAcsActionProcessorException
     */
    public function __invoke(
        DeliveryExecution $deliveryExecution,
        AcsControlInterface $acsControl,
    ): AcsControlResultInterface {
        if (
            ($deliveryExecution->isStateFinal() || $deliveryExecution->isStateInitial())
            && !in_array(
                $acsControl->getAction(),
                [
                    AcsControlInterface::ACTION_RESUME,
                    AcsControlInterface::ACTION_FLAG,
                ],
            )
        ) {
            /** @question should we check delivery execution can be controlled? [@emgolubev] */
            throw new NotControllableDeliveryExecutionException(
                'Delivery execution\'s state does not permit this action',
            );
        }

        foreach ($this->acsActionProcessors as $acsActionProcessor) {
            if (
                !$acsActionProcessor instanceof AcsActionProcessorInterface
                || !$acsActionProcessor->supports($acsControl)
            ) {
                continue;
            }
            $this->auditDeliveryExecutionLogger->info(sprintf(
                '[%s] Processing "%s" ACS action...',
                $deliveryExecution->getId(),
                $acsControl->getAction(),
            ));

            try {
                $acsControlResult = $acsActionProcessor->process($acsControl, $deliveryExecution);
            } catch (Exception $exception) {
                throw new LtiAcsActionProcessorException(
                    sprintf(
                        '[%s] Failed to execute "%s" ACS action: %s',
                        $deliveryExecution->getId(),
                        $acsControl->getAction(),
                        $exception->getMessage(),
                    ),
                    $exception,
                );
            }
            $this->auditDeliveryExecutionLogger->info(sprintf(
                '[%s] "%s" ACS action has been successfully processed',
                $deliveryExecution->getId(),
                $acsControl->getAction(),
            ));


            $this->eventDispatcher->dispatch(new AcsControlProcessedEvent(
                $deliveryExecution,
                $acsControlResult->getStatus(),
                $acsControl,
            ));

            return $acsControlResult;
        }

        throw new NotSupportedAssessmentControlAction(
            sprintf('"%s" ACS action is not supported', $acsControl->getAction()),
        );
    }
}
