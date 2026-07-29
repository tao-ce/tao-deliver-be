<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

use OpenTelemetry\Contrib\Instrumentation\Symfony\HttpClientInstrumentation;
use OpenTelemetry\Contrib\Instrumentation\Symfony\SymfonyInstrumentation;
use OpenTelemetry\SDK\Sdk;

if (Sdk::isInstrumentationDisabled('app') === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error(
        'The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Symfony auto-instrumentation',
        E_USER_WARNING
    );

    return;
}

SymfonyInstrumentation::register();
HttpClientInstrumentation::register();
