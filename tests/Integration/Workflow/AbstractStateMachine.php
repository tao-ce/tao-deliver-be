<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Workflow;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

abstract class AbstractStateMachine extends KernelTestCase
{
    /** @var WorkflowInterface */
    private $subject;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->subject = static::getContainer()->get('state_machine.' . $this->getStateMachineIdentifier());
    }

    public function testPlaces(): void
    {
        $this->assertSame($this->getExpectedPlaces(), array_values($this->subject->getDefinition()->getPlaces()));
    }

    public function testTransitions(): void
    {
        $configuredTransitions = [];

        foreach ($this->subject->getDefinition()->getTransitions() as $transition) {
            $configuredTransitions[$transition->getName()] = [
                'froms' => array_unique(
                    array_merge($configuredTransitions[$transition->getName()]['froms'] ?? [], $transition->getFroms()),
                ),
                'tos' => array_unique(
                    array_merge($configuredTransitions[$transition->getName()]['tos'] ?? [], $transition->getTos()),
                ),
            ];
        }

        $this->assertSame($this->getExpectedTransitions(), $configuredTransitions);
    }

    /**
     * @see config/packages/workflow.yaml
     */
    abstract protected function getStateMachineIdentifier(): string;

    abstract protected function getExpectedTransitions(): array;

    abstract protected function getExpectedPlaces(): array;
}
