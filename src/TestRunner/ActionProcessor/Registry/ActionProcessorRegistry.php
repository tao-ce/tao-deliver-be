<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor\Registry;

use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use InvalidArgumentException;

class ActionProcessorRegistry
{
    /** @var ActionProcessorInterface[] */
    private $actionProcessors = [];

    public function __construct(iterable $actionProcessors = [])
    {
        foreach ($actionProcessors as $actionProcessor) {
            $this->add($actionProcessor);
        }
    }

    public function add(ActionProcessorInterface $actionProcessor): self
    {
        $this->actionProcessors[$actionProcessor->getActionName()] = $actionProcessor;

        return $this;
    }

    public function get(string $actionName): ActionProcessorInterface
    {
        if (!$this->has($actionName)) {
            throw new InvalidArgumentException(sprintf('No action processor found for action name: %s', $actionName));
        }

        return $this->actionProcessors[$actionName];
    }

    public function has(string $actionName): bool
    {
        return array_key_exists($actionName, $this->actionProcessors);
    }
}
