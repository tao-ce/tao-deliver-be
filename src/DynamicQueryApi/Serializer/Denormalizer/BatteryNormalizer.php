<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Serializer\Denormalizer;

use App\DynamicQueryApi\Model\Battery;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BatteryNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public const CONTEXT_VIEW = 'view';
    public const VIEW_LTI_DEEP_LINKING = 'lti_deep_linking';

    private const KEY_ID = '_id';
    private const KEY_NAME = 'name';
    private const KEY_DESCRIPTION = 'description';
    private const KEY_MODE = 'mode';
    private const KEY_STATUS = 'status';
    private const KEY_TENANT_ID = 'tenantId';
    private const KEY_DELIVERIES = 'deliveries';
    private const KEY_DELIVERY_IDS = 'deliveryIds';
    private const KEY_DELIVERY_ID = 'id';

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Battery;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            Battery::class => true,
        ];
    }

    /**
     * @param Battery $object
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
            self::KEY_NAME => $object->getName(),
            self::KEY_DESCRIPTION => $object->getDescription(),
            self::KEY_MODE => $object->getMode(),
            self::KEY_STATUS => $object->getStatus(),
            self::KEY_TENANT_ID => $object->getTenantId(),
            self::KEY_DELIVERY_IDS => $object->getDeliveryIds(),
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === Battery::class;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Battery
    {
        $this->validateDenormalizableData($data);

        return new Battery(
            $data[self::KEY_ID],
            $data[self::KEY_NAME],
            $data[self::KEY_DESCRIPTION],
            $data[self::KEY_MODE],
            $data[self::KEY_STATUS],
            $data[self::KEY_TENANT_ID],
            array_map(static function (array $deliveryData) {
                return $deliveryData[self::KEY_DELIVERY_ID];
            }, $data[self::KEY_DELIVERIES]),
        );
    }

    private function normalizeForLtiDeepLinking(Battery $battery): array
    {
        return [
            'id' => $battery->getId(),
            'name' => $battery->getName(),
            'nrOfDeliveries' => count($battery->getDeliveryIds()),
        ];
    }

    private function validateDenormalizableData($data): void
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot denormalize data into %s: data is not an array',
                Battery::class,
            ));
        }

        $expectedKeys = [
            self::KEY_ID,
            self::KEY_NAME,
            self::KEY_DESCRIPTION,
            self::KEY_MODE,
            self::KEY_STATUS,
            self::KEY_TENANT_ID,
            self::KEY_DELIVERIES,
        ];
        $missingExpectedKeys = array_diff($expectedKeys, array_keys($data));

        if (count($missingExpectedKeys) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Cannot denormalize data into %s: the following mandatory keys are missing: %s',
                Battery::class,
                implode(', ', $missingExpectedKeys),
            ));
        }

        foreach ($data[self::KEY_DELIVERIES] as $deliveryData) {
            if (!array_key_exists(self::KEY_DELIVERY_ID, $deliveryData)) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot denormalize data into %s: mandatory "%s" key is missing from "%s" array',
                    Battery::class,
                    self::KEY_DELIVERY_ID,
                    self::KEY_DELIVERIES,
                ));
            }
        }
    }
}
