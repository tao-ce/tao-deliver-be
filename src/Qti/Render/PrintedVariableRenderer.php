<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Render;

use App\Qti\Render\Twig\QtiExtension;
use DOMDocumentFragment;
use qtism\data\content\PrintedVariable;
use qtism\data\QtiComponent;
use qtism\runtime\rendering\markup\xhtml\PrintedVariableRenderer as BasePrintedVariableRenderer;

class PrintedVariableRenderer extends BasePrintedVariableRenderer
{
    protected function appendChildren(DOMDocumentFragment $fragment, QtiComponent $component, $base = ''): void
    {
        $fragment->firstChild->appendChild(
            $fragment->ownerDocument->createTextNode(
                $this->getPrintedVariableTwigExpression($component),
            ),
        );
    }

    /**
     * @param PrintedVariable $component
     */
    private function getPrintedVariableTwigExpression(QtiComponent $component): string
    {
        return sprintf(
            '{{ %s(%s, %s, %s, %s, %s, %s, %s, %s, %s) }}',
            QtiExtension::FUNCTION_GET_PRINTED_VARIABLE,
            'testSession',
            $this->renderFunctionArgument($component->getIdentifier()),
            $this->renderFunctionArgument($component->getFormat()),
            $this->renderFunctionArgument($component->mustPowerForm()),
            $this->renderFunctionArgument($component->getBase()),
            $this->renderFunctionArgument($component->getIndex()),
            $this->renderFunctionArgument($component->getDelimiter()),
            $this->renderFunctionArgument($component->getField()),
            $this->renderFunctionArgument($component->getMappingIndicator()),
        );
    }

    private function renderFunctionArgument($variable): string
    {
        switch (gettype($variable)) {
            case 'string':
                return sprintf('"%s"', $variable);

            case 'boolean':
                return $variable ? 'true' : 'false';

            default:
                return sprintf('%s', $variable);
        }
    }
}
