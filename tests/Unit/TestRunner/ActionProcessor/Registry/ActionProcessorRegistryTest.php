<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor\Registry;

use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\Registry\ActionProcessorRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ActionProcessorRegistryTest extends TestCase
{
    public function testItCanBeConstructedWithActionProcessors(): void
    {
        $action1 = $this->createActionProcessorMock('action1');
        $action2 = $this->createActionProcessorMock('action2');

        $subject = new ActionProcessorRegistry([
            $action1,
            $action2,
        ]);

        $this->assertTrue($subject->has($action1->getActionName()));
        $this->assertTrue($subject->has($action2->getActionName()));
    }

    public function testItCanAddAndRetrieveAnActionProcessor(): void
    {
        $subject = new ActionProcessorRegistry();
        $action = $this->createActionProcessorMock('action');

        $subject->add($action);
        $this->assertTrue($subject->has('action'));

        $gotAction = $subject->get('action');
        $this->assertSame($action, $gotAction);
    }

    public function testItThrowsAnExceptionWhenTryingToGetANonExistingActionProcessor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No action processor found for action name: invalid');

        (new ActionProcessorRegistry())->get('invalid');
    }

    private function createActionProcessorMock(string $actionName): ActionProcessorInterface
    {
        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);

        $actionProcessorMock
            ->method('getActionName')
            ->willReturn($actionName);

        return $actionProcessorMock;
    }
}
