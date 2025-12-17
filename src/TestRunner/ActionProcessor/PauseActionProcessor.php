<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlReason;
use App\Lti\Event\AcsControlProcessedEvent;
use App\Lti\Proctoring\AcsActionProcessor\AcsPauseActionProcessor;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\Control\ControlType;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use App\TestRunner\Generator\TestContextGenerator;
use Carbon\Carbon;
use OAT\Library\Lti1p3Proctoring\Model\AcsControl;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PauseActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'pause';

    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly TestContextGenerator $testContextGenerator,
        private readonly AcsPauseActionProcessor $actionProcessor,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $parameters = $actionParameters['parameters'];
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $this->actionProcessor->process(
            new AcsControl(
                $deliveryExecution->getResourceLink(),
                $deliveryExecution->getUserId(),
                AcsControlInterface::ACTION_PAUSE,
                Carbon::now(),
            ),
            $deliveryExecution,
            (float)($parameters['itemDuration'] ?? .0),
        );
        $this->eventDispatcher->dispatch(new DeliveryExecutionControlEvent(
            $deliveryExecution,
            ControlType::PAUSE,
            new DeliveryExecutionControlReason($parameters['plugin'] ?? ''),
        ));

        return $this->getActionProcessorResponse($actionParameters, [
            'testContext' => $this->testContextGenerator->generate(
                $testSession,
                $deliveryExecution,
            ),
        ]);
    }
}
