<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Serializer;

use App\Messenger\Message\NormalizableInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

readonly class ExternalMessageSerializer implements SerializerInterface
{
    public function __construct(
        private string $type,
        private ObjectNormalizer $normalizer,
        private LoggerInterface $auditPlatformLogger,
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        return new Envelope(
            $this->normalize($encodedEnvelope),
            [
                new SerializerStamp($encodedEnvelope),
            ],
        );
    }

    public function encode(Envelope $envelope): array
    {
        return $envelope->last(SerializerStamp::class)->getContext();
    }

    private function normalize(array $encodedEnvelope): object
    {
        $this->auditPlatformLogger->info(
            sprintf(
                'Received external %s message: %s',
                $this->type,
                json_encode($encodedEnvelope),
            ),
            [
                'message' => $encodedEnvelope,
            ],
        );

        if (is_a($this->type, NormalizableInterface::class, true)) {
            return $this->type::fromArray($encodedEnvelope);
        }

        return $this->normalizer->denormalize(
            $encodedEnvelope,
            $this->type,
        );
    }
}
