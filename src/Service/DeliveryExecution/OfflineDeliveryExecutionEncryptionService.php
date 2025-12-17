<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionEncryptionServiceInterface;
use App\Service\Encryption\Contract\EncryptorInterface;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiDatatype;
use qtism\common\datatypes\QtiString;
use qtism\runtime\common\Container;
use qtism\runtime\common\ResponseVariable;
use UnexpectedValueException;

readonly class OfflineDeliveryExecutionEncryptionService implements DeliveryExecutionEncryptionServiceInterface
{
    public function __construct(
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private EncryptorInterface $encryptor,
        private LoggerInterface $platformLogger,
    ) {
    }

    public function encrypt(DeliveryExecution $deliveryExecution, string $encryptionKey): DeliveryExecution
    {
        $assessmentSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $this->encryptor->setEncryptionKey($encryptionKey);

        $assessmentItemSessionStorage = $assessmentSession->getAssessmentItemSessionStore();
        try {
            $list = $assessmentItemSessionStorage->getAllAssessmentItemSessions();
            foreach ($list as $assessmentItemSession) {
                foreach ($assessmentItemSession->getResponseVariables(false) as $responseVariable) {
                    if (!$responseVariable instanceof ResponseVariable) {
                        continue;
                    }
                    $this->resolveResponseVariableEncryption($responseVariable);
                }
            }
        } catch (OutOfBoundsException | UnexpectedValueException $e) {
            $this->platformLogger->notice(
                sprintf(
                    'Error while encrypting response variables for delivery execution %s',
                    $deliveryExecution->getId(),
                ),
                ['exception' => $e],
            );
        }

        $this->deliveryExecutionPropertyService->persistTestSession($assessmentSession);
        return $deliveryExecution->clearAllItemState();
    }

    private function resolveResponseVariableEncryption(ResponseVariable $responseVariable): void
    {
        $value = $responseVariable->getValue();
        if ($value instanceof Container) {
            foreach ($value as $v) {
                $this->encryptQtiStringValue($v);
            }
            return;
        }
        $this->encryptQtiStringValue($value);
    }

    private function encryptQtiStringValue(?QtiDatatype $value): void
    {
        if (isset($value) && $value instanceof QtiString) {
            $encodedStringValue = base64_encode($this->encryptor->encrypt($value->getValue()));
            $value->setValue($encodedStringValue);
        }
    }
}
