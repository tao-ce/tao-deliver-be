<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/public']);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PER-CS2.0' => true,
        'single_line_empty_body' => false,
        'cast_spaces' => ['space' => 'none'],
    ])
    ->setFinder($finder);
