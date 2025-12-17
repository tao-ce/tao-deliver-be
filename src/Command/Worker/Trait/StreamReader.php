<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Worker\Trait;

use JsonException;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;

/**
 * @deprecated Should be removed with worker commands
 */

trait StreamReader
{
    protected function readDecodedPayload(InputInterface $input): array
    {
        try {
            return json_decode($this->readPayload($input), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to decode the input', previous: $e);
        }
    }

    protected function readPayload(InputInterface $input): string
    {
        $inputStream = (($input instanceof StreamableInputInterface) ? $input->getStream() : null) ?? STDIN;
        stream_set_blocking($inputStream, true);

        $payload = stream_get_contents($inputStream);
        if (!$payload) {
            throw new RuntimeException('This command requires STDIN stream payload');
        }

        return $payload;
    }
}
