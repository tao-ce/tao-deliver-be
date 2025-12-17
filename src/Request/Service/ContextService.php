<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Request\Service;

use App\Request\Domain\Context;
use App\Request\Extractor\Contract\ContextExtractorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ContextService
{
    private ?Context $context = null;

    /**
     * @param ContextExtractorInterface[] $contextExtractors
     */
    public function __construct(private iterable $contextExtractors, private RequestStack $requestStack)
    {
    }

    public function fetch(): Context
    {
        if (null === $this->context) {
            $this->initContext();
        }

        return $this->context;
    }

    private function initContext(): void
    {
        $this->context = new Context();

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        foreach ($this->contextExtractors as $contextExtractor) {
            if ($contextExtractor->supports($request)) {
                $this->context = $contextExtractor->extract();

                return;
            }
        }
    }
}
