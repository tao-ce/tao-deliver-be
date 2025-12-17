<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Serializer\Denormalizer;

use App\DynamicQueryApi\Model\SearchResponse;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class SearchResponseDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const CONTEXT_DATA_TYPE = 'dataType';
    private const KEY_DATA = 'data';
    private const KEY_TOTAL_RESULTS = 'totalResults';
    private const KEY_LAST_ID = 'lastId';

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === SearchResponse::class;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): SearchResponse
    {
        $this->validateData($data);

        /*
         * `SearchResponse` could return different type of data, therefore it's mandatory to define
         * the desired type in the `dataType` context parameter
         */
        if (!array_key_exists(self::CONTEXT_DATA_TYPE, $context)) {
            throw new InvalidArgumentException(sprintf(
                '%s context parameter is missing for %s.',
                self::CONTEXT_DATA_TYPE,
                self::class,
            ));
        }

        return new SearchResponse(
            $this->denormalizer->denormalize($data['data'], sprintf('%s[]', $context[self::CONTEXT_DATA_TYPE]), 'json'),
            $data['totalResults'],
            $data['lastId'],
        );
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            SearchResponse::class => true,
        ];
    }

    private function validateData($data): void
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot denormalize data into %s: data is not an array',
                SearchResponse::class,
            ));
        }

        $expectedKeys = [
            self::KEY_DATA,
            self::KEY_TOTAL_RESULTS,
            self::KEY_LAST_ID,
        ];
        $missingExpectedKeys = array_diff($expectedKeys, array_keys($data));

        if (count($missingExpectedKeys) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Cannot denormalize data into %s: the following mandatory keys are missing: %s',
                SearchResponse::class,
                implode(', ', $missingExpectedKeys),
            ));
        }
    }
}
