<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Registry;

use App\Generator\Asset\LocalSignedUrlGenerator;
use App\Generator\Asset\SignedUrlGeneratorInterface;
use InvalidArgumentException;

class SignedUrlGeneratorRegistry
{
    /** @var SignedUrlGeneratorInterface[] */
    private $generators;

    public function __construct(iterable $taggedGenerators = [])
    {
        /** @var SignedUrlGeneratorInterface $taggedGenerator */
        foreach ($taggedGenerators as $taggedGenerator) {
            $this->addGenerator($taggedGenerator);
        }
    }

    public function getGenerator(string $name): SignedUrlGeneratorInterface
    {
        if (isset($this->generators[$name])) {
            return $this->generators[$name];
        }

        if (isset($this->generators[LocalSignedUrlGenerator::NAME])) {
            return $this->generators[LocalSignedUrlGenerator::NAME];
        }

        throw new InvalidArgumentException(sprintf('No url generator associated to the name given %s', $name));
    }

    private function addGenerator(SignedUrlGeneratorInterface $generator): self
    {
        $this->generators[$generator->getName()] = $generator;

        return $this;
    }
}
