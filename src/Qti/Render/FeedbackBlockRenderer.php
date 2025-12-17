<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Render;

use DOMDocumentFragment;
use qtism\data\QtiComponent;
use qtism\runtime\rendering\markup\xhtml\FeedbackBlockRenderer as BaseFeedbackBlockRenderer;

class FeedbackBlockRenderer extends BaseFeedbackBlockRenderer
{
    protected function appendChildren(DOMDocumentFragment $fragment, QtiComponent $component, $base = ''): void
    {
        // Using getPrintedVariable because it is the easiest way to retrieve any outcome variable from test session
        $fragment->firstChild->insertBefore(
            $fragment->ownerDocument->createTextNode(
                sprintf(
                    '{%% if getPrintedVariable(testSession, "%s", "%%s", false, 10, -1, ";", "", "=") == "%s" %%}',
                    $component->getOutcomeIdentifier(),
                    $component->getIdentifier(),
                ),
            ),
        );

        parent::appendChildren($fragment, $component, $base);

        $fragment->firstChild->appendChild(
            $fragment->ownerDocument->createTextNode('{% endif %}'),
        );
    }
}
