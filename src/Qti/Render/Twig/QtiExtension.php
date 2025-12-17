<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Render\Twig;

use qtism\runtime\common\State;
use qtism\runtime\rendering\markup\Utils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class QtiExtension extends AbstractExtension
{
    public const FUNCTION_GET_PRINTED_VARIABLE = 'getPrintedVariable';

    public function getFunctions(): array
    {
        return [
            new TwigFunction(self::FUNCTION_GET_PRINTED_VARIABLE, [
                $this,
                self::FUNCTION_GET_PRINTED_VARIABLE,
            ]),
        ];
    }

    public function getPrintedVariable(
        State $context,
        $identifier,
        $format,
        $powerForm,
        $base,
        $index,
        $delimiter,
        $field,
        $mappingIndicator,
    ): string {
        return Utils::printVariable(
            $context,
            $identifier,
            $format,
            $powerForm,
            $base,
            $index,
            $delimiter,
            $field,
            $mappingIndicator,
        );
    }
}
