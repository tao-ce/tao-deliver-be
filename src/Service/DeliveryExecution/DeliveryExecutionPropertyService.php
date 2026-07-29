<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Service\Infrastructure\Contract\MemoizedService;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use LogicException;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\RouteItem;

/**
 * The purpose of this service is to offer access to runtime delivery-execution properties, including @see AssessmentTestSession.
 * Direct access to @see TestSessionAccessor should be replaced by this service usage.
 */
class DeliveryExecutionPropertyService implements MemoizedService
{
    /** @var TestSessionAccessor[] */
    private array $testSessionAccessors;

    /** @var AssessmentTestSession[] */
    private array $testSessions;

    public function __construct(
        private readonly TestSessionAccessorFactory $testSessionAccessorFactory,
        private readonly LtiCustomSettings $ltiCustomSettingsReader,
        private readonly AssessmentTestSessionFactory $assessmentTestSessionFactory,
    ) {
        $this->flush();
    }

    public function flush(): void
    {
        $this->testSessionAccessors = [];
        $this->testSessions = [];
    }

    /**
     * @return string[]
     */
    public function getAllItemCategories(DeliveryExecution $deliveryExecution): array
    {
        $categoriesMap = [];
        /** @var RouteItem $routeItem */
        foreach ($this->fetchTestSession($deliveryExecution)->getRoute()->getAllRouteItems() as $routeItem) {
            $categoriesMap += array_flip($routeItem->getAssessmentItemRef()->getCategories()->getArrayCopy());
        }

        return array_keys($categoriesMap);
    }

    public function getQtiTestTitle(DeliveryExecution $deliveryExecution): string
    {
        return $this->retrieveTestSession($deliveryExecution, false)->getAssessmentTest()->getTitle();
    }

    public function getTestTitle(DeliveryExecution $deliveryExecution): string
    {
        foreach (
            $this->ltiCustomSettingsReader->getCustomTitles(
                $deliveryExecution->getLtiLaunchParameters(),
            ) ?? [] as $customTitle
        ) {
            $type = $customTitle['type'] ?? null;
            $label = $customTitle['label'] ?? null;
            if ($type === 'test' && !empty($label)) {
                return $label;
            }
        }

        return $this->getQtiTestTitle($deliveryExecution);
    }

    public function fetchTestSession(DeliveryExecution $deliveryExecution, bool $initAllItems = false): AssessmentTestSession
    {
        $id = $deliveryExecution->getId();
        if (!isset($this->testSessions[$id])) {
            $this->testSessions[$id] = $this->retrieveTestSession($deliveryExecution, $initAllItems);
        }

        return $this->testSessions[$id];
    }

    public function persistTestSession(AssessmentTestSession $testSession): void
    {
        $id = $testSession->getSessionId();
        if (!isset($this->testSessionAccessors[$id])) {
            throw new LogicException('Assessment session must fist be fetched.');
        }

        $this->testSessionAccessors[$id]->persist($testSession);
        $this->testSessions[$id] = $testSession;
    }

    private function createTestSessionAccessor(DeliveryExecution $deliveryExecution): TestSessionAccessor
    {
        $id = $deliveryExecution->getId();
        if (!isset($this->testSessionAccessors[$id])) {
            $this->testSessionAccessors[$id] = $this->testSessionAccessorFactory->create($deliveryExecution);
        }

        return $this->testSessionAccessors[$id];
    }

    private function retrieveTestSession(DeliveryExecution $deliveryExecution, bool $initAllItems): AssessmentTestSession
    {
        $testSessionAccessor = $this->createTestSessionAccessor($deliveryExecution);
        $testSession = $deliveryExecution->getQtiSdkEncodedTestSession()
            ? $testSessionAccessor->retrieve($deliveryExecution->getId())
            : $testSessionAccessor->instantiate(sessionId: $deliveryExecution->getId());

        return $this->assessmentTestSessionFactory->createByLtiLaunchParams(
            $testSession,
            $deliveryExecution->getLtiLaunchParameters(),
            $initAllItems,
        );
    }
}
