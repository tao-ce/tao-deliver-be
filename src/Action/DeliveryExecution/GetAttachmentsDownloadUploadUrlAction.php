<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\Attachment\AttachmentUrlGenerator;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\Asset\UploadedFilePathFormatterInterface;
use App\Validator\DeliveryExecution\GetAttachmentsDownloadUploadUrlValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

readonly class GetAttachmentsDownloadUploadUrlAction implements DeliveryExecutionSessionController
{
    public const FILE_ID_PREFIX = 'attachment';

    public function __construct(
        private GetAttachmentsDownloadUploadUrlValidator $requestValidator,
        private SerializerResponder $responder,
        private AttachmentUrlGenerator $urlGenerator,
        private UploadedFilePathFormatterInterface $uploadedFilePathFormatter,
    ) {
    }

    public function __invoke(Request $request, DeliveryExecution $deliveryExecution): JsonResponse
    {
        $this->validateDeliveryExecutionAccessibility($deliveryExecution);

        $this->requestValidator->setDeliveryExecution($deliveryExecution);
        $parameters = $this->requestValidator->getValidatedRequestParameters($request);
        $path = $this->uploadedFilePathFormatter->format(
            $deliveryExecution->getId(),
            $parameters['itemId'],
            $parameters['responseId'],
            uniqid(self::FILE_ID_PREFIX, true),
        );

        return $this->responder->createJsonResponse([
            'success' => true,
            'data' => [
                'uploadMethod' => $this->urlGenerator->getUploadMethod(),
                'uploadUrl' => $this->urlGenerator->generateUploadUrl($path),
                'downloadUrl' => $this->urlGenerator->generateDownloadUrl($path),
                'id' => $path,
            ],
        ]);
    }

    /**
     * @param DeliveryExecution $deliveryExecution
     */
    private function validateDeliveryExecutionAccessibility(DeliveryExecution $deliveryExecution): void
    {
        if (DeliveryExecution::STATUS_CLOSED === $deliveryExecution->getStatus()) {
            throw new HttpDeliveryExecutionClosedException(
                sprintf(
                    'Access to this delivery execution [%s] is not accessible because it is closed',
                    $deliveryExecution->getId(),
                ),
            );
        }
    }
}
