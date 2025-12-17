<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Serializer\Denormalizer;

use App\DynamicQueryApi\Model\Delivery;
use Carbon\Carbon;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public const CONTEXT_VIEW = 'view';
    public const VIEW_LTI_DEEP_LINKING = 'lti_deep_linking';

    private const KEY_ID = '_id';
    private const KEY_QTI_ITEMS_MAPPING = 'qtiItemsMapping';
    private const KEY_TENANT_ID = 'tenantId';
    private const KEY_CONFIGURATION = 'configuration';
    private const KEY_COMPACT_TEST_FILE_PATH = 'compactTestFilePath';
    private const KEY_CREATED_AT = 'createdAt';

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Delivery;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            Delivery::class => true,
        ];
    }

    /**
     * @param Delivery $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        if (isset($context[self::CONTEXT_VIEW])) {
            return match ($context[self::CONTEXT_VIEW]) {
                self::VIEW_LTI_DEEP_LINKING => $this->normalizeForLtiDeepLinking($object),
                default => throw new InvalidArgumentException(sprintf(
                    'Unknown view "%s" in serialization context. Supported views are: "%s".',
                    $context[self::CONTEXT_VIEW],
                    implode('", "', [self::VIEW_LTI_DEEP_LINKING]),
                )),
            };
        }

        return [
            self::KEY_ID => $object->getId(),
            self::KEY_QTI_ITEMS_MAPPING => $object->getQtiItemsMapping(),
            self::KEY_TENANT_ID => $object->getTenantId(),
            self::KEY_COMPACT_TEST_FILE_PATH => $object->getCompactTestFilePath(),
            self::KEY_CONFIGURATION => $object->getConfiguration(),
            self::KEY_CREATED_AT => $object->getCreatedAt(),
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === Delivery::class;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Delivery
    {
        $this->validateDenormalizableData($data);

        return new Delivery(
            $data[self::KEY_ID],
            $data[self::KEY_QTI_ITEMS_MAPPING],
            $data[self::KEY_TENANT_ID],
            $data[self::KEY_COMPACT_TEST_FILE_PATH],
            $data[self::KEY_CONFIGURATION],
            // convert milliseconds to seconds as we store the timestamp in `epoch_millis` format in Elasticsearch
            Carbon::createFromTimestamp($data[self::KEY_CREATED_AT] / 1000),
        );
    }

    private function normalizeForLtiDeepLinking(Delivery $delivery): array
    {
        $label = array_key_exists('label', $delivery->getConfiguration())
            ? $delivery->getConfiguration()['label']
            : 'undefined';

        return [
            'id' => $delivery->getId(),
            'name' => $label,
        ];
    }

    private function validateDenormalizableData($data): void
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot denormalize data into %s: data is not an array',
                Delivery::class,
            ));
        }

        $expectedKeys = [
            self::KEY_ID,
            self::KEY_QTI_ITEMS_MAPPING,
            self::KEY_TENANT_ID,
            self::KEY_COMPACT_TEST_FILE_PATH,
            self::KEY_CONFIGURATION,
            self::KEY_CREATED_AT,
        ];
        $missingExpectedKeys = array_diff($expectedKeys, array_keys($data));

        if (count($missingExpectedKeys) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Cannot denormalize data into %s: the following mandatory keys are missing: %s',
                Delivery::class,
                implode(', ', $missingExpectedKeys),
            ));
        }
    }
}
