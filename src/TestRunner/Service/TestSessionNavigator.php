<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use App\TestRunner\Event\Control\ControlType;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use qtism\data\storage\php\PhpStorageException;
use qtism\runtime\tests\AssessmentItemSessionException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;

readonly class TestSessionNavigator
{
    public const DIRECTION_NEXT = 'next';
    public const DIRECTION_BACK = 'previous';
    public const DIRECTION_JUMP = 'jump';
    public const SCOPE_ITEM = 'item';
    public const SCOPE_SECTION = 'section';
    public const SCOPE_TEST_PART = 'testPart';
    public const SCOPE_TEST = 'test';

    // legacy scopes
    public const LEGACY_SCOPE_ITEM = 'assessmentItem';
    public const LEGACY_SCOPE_SECTION = 'assessmentSection';
    public const LEGACY_SCOPE_TEST = 'assessmentTest';

    public function __construct(
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    /**
     * @throws AssessmentItemSessionException
     * @throws AssessmentTestSessionException
     * @throws PhpStorageException
     */
    public function navigate(
        DeliveryExecution $deliveryExecution,
        string $scope,
        string $direction,
        ?int $ref = null,
    ): void {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $previousItemId = $testSession->isRunning() ? $testSession->getCurrentAssessmentItemRef()->getIdentifier() : '';
        switch ($scope) {
            case static::SCOPE_ITEM:
            case static::LEGACY_SCOPE_ITEM:
                $this->navigateItem($testSession, $direction, $ref);
                break;

            case static::SCOPE_SECTION:
            case static::LEGACY_SCOPE_SECTION:
                $this->navigateSection($testSession, $direction);
                break;

            case static::SCOPE_TEST_PART:
                $this->navigateTestPart($testSession, $direction);
                break;

            case static::SCOPE_TEST:
            case static::LEGACY_SCOPE_TEST:
                $testSession->endTestSession();
                break;

            default:
                throw new InvalidArgumentException(sprintf('Invalid scope parameter: %s', $scope));
        }

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has navigated from item [%s] to item [%s]; direction [%s]; scope [%s]',
                $deliveryExecution->getId(),
                $previousItemId,
                $testSession->isRunning() ? $testSession->getCurrentAssessmentItemRef()->getIdentifier() : '<end>',
                $direction,
                $scope,
            ),
        );
        if ($testSession->isRunning()) {
            $this->eventDispatcher->dispatch(
                new DeliveryExecutionControlEvent(
                    $deliveryExecution,
                    ControlType::NAVIGATION,
                ),
            );
        }
    }

    public function navigateToItemRef(DeliveryExecution $deliveryExecution, string $itemIdentifier): bool
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($testSession->getAssessmentItemSessions($itemIdentifier) === false) {
            return false;
        }

        $testSession->setAlwaysAllowJumps(true);

        $itemSessions = $testSession->getAssessmentItemSessionStore()->getAllAssessmentItemSessions();

        $itemPosition = 0;

        /** @var AssessmentItemSession $itemSession */
        foreach ($itemSessions as $itemSession) {
            if ($itemSession->getAssessmentItem()->getIdentifier() === $itemIdentifier) {
                $this->navigateItem($testSession, static::DIRECTION_JUMP, $itemPosition);
                break;
            }

            $itemPosition++;
        }

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
        return true;
    }

    /**
     * @throws AssessmentTestSessionException
     */
    private function navigateItem(
        AssessmentTestSession $testSession,
        string $direction,
        ?int $ref = null,
    ): void {
        switch ($direction) {
            case static::DIRECTION_NEXT:
                $testSession->moveNext();
                break;

            case static::DIRECTION_BACK:
                if (!$testSession->canMoveBackward()) {
                    throw new InvalidArgumentException('It is not possible to move backward');
                }
                $testSession->moveBack();
                break;

            case static::DIRECTION_JUMP:
                if (null === $ref) {
                    throw new InvalidArgumentException(sprintf('Invalid ref parameter: %d', $ref));
                }
                $testSession->jumpTo($ref);
                break;

            default:
                throw new InvalidArgumentException(sprintf('Invalid move direction: %s', $direction));
        }
    }

    /**
     * @throws AssessmentTestSessionException
     */
    private function navigateSection(AssessmentTestSession $testSession, string $direction): void
    {
        $this->checkNextDirectionProvided($direction);
        $testSession->moveNextAssessmentSection();
    }

    /**
     * @throws AssessmentTestSessionException
     */
    private function navigateTestPart(AssessmentTestSession $testSession, string $direction): void
    {
        $this->checkNextDirectionProvided($direction);
        $testSession->moveNextTestPart();

        if (!$testSession->routeMatchesPreconditions()) {
            $testSession->moveNext();
        }
    }

    private function checkNextDirectionProvided(string $direction): void
    {
        if (static::DIRECTION_NEXT !== $direction) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid move direction: %s, only "%s" is supported',
                    $direction,
                    static::DIRECTION_NEXT,
                ),
            );
        }
    }
}
