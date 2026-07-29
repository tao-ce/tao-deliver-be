<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use JsonSerializable;

class DeliveryExecutionControlReason implements JsonSerializable
{
    private const FALLBACK_CODE = 999;

    private int $code;
    private ?string $message;

    public function __construct(string $reason, ?int $code = null, array $details = [])
    {
        if ($code !== null) {
            $this->code = $code;
            $this->message = $reason;
            return;
        }

        [$this->code, $this->message] = match ($reason) {
            'blur-attempt' => [20060, '[Pause On Blur] Test taker tried to move to a different tab/app.'],
            'exit-fullscreen-attempt' => [20060, '[Full Screen] Test taker tried to go out from full screen mode.'],
            'screenshot-attempt' => [20999, '[Prevent Screenshot] Test taker tried to take a screenshot.'],
            'context-menu-call-attempt' => [20999, '[Disable Right Click] Test taker tried to use right click menu.'],
            'copy-attempt' => [20999, '[Disable Commands] Test taker tried to use a forbidden command: `copy`.'],
            'cut-attempt' => [20999, '[Disable Commands] Test taker tried to use a forbidden command: `cut`.'],
            'paste-attempt' => [20999, '[Disable Commands] Test taker tried to use a forbidden command: `paste`.'],
            'ip-change' => [20999, 'IP address change detected.'],
            'pauseOnBlur' => [20060, '[Security Plugin] Test execution was paused because test taker tried to move to a different tab/app.'],
            'forceFullscreen' => [20060, '[Security Plugin] Test execution was paused because test taker tried to go out from full screen mode.'],
            'lockdown-missing' => [20999, '[Lockdown Browser] Missing lockdown browser.'],
            'lockdown-version' => [20999, '[Lockdown Browser] Lockdown browser version mismatch. Required: ' . ($details['required'] ?? '') . ', Detected: ' . ($details['detected'] ?? '') . '.'],
            'lockdown-processes-on-launch' => [20999,
                sprintf("[Lockdown Browser] Prohibited processes detected: %s.", $details['processes'] ?? ''),
            ],
            'lockdown-processes-after-launch' => [20999,
                sprintf(
                    "[Lockdown Browser] Prohibited processes detected after launch: %s.",
                    $details['processes'] ?? '',
                ),
            ],
            'lockdown-breach' => [20999,
                sprintf(
                    "[Lockdown Browser] Breach event. Device info: %s. Process list: %s.",
                    $details['deviceInfo'] ?? '',
                    $details['processList'] ?? '',
                ),
            ],
            'lockdown-breach-pause' => [20999, '[Lockdown Browser] Test execution was paused because breach event was detected.'],
            default => [self::FALLBACK_CODE, null],
        };
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
