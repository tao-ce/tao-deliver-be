<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Render;

use qtism\runtime\rendering\markup\xhtml\XhtmlRenderingEngine as BaseXhtmlRenderingEngine;

class XhtmlRenderingEngine extends BaseXhtmlRenderingEngine
{
    public function __construct()
    {
        parent::__construct();

        $this->registerRenderer('printedVariable', new PrintedVariableRenderer());
        $this->registerRenderer('feedbackBlock', new FeedbackBlockRenderer());
    }
}
