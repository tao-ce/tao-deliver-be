<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ContentServiceApi\Gateway;

use App\ContentServiceApi\Exception\ContentServiceApiException;
use App\Environment\FeatureFlagAdapterInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ContentServiceApiGateway
{
    private const ITEM_CONTENT_UPLOAD_ENABLED = 'ITEM_CONTENT_UPLOAD_ENABLED';

    public function __construct(
        private readonly HttpClientInterface $client,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
        private readonly LoggerInterface $logger,
        private readonly string $contentServiceApiUrl,
    ) {
    }

    public function getUploadedFiles(string $tenantId, array $ids, int $ttlInMs): array
    {
        if (!$this->contentServiceApiUrl || !$ids) {
            return [];
        }

        return $this->client->request(
            'GET',
            sprintf(
                "%s/api/v1/tenants/%s/assets?%s",
                $this->contentServiceApiUrl,
                $tenantId,
                http_build_query(['id' => $ids, 'ttl' => $ttlInMs, 'routeViaCdn' => 1], arg_separator: '&'),
            ),
        )->toArray();
    }

    /**
     * @throws ContentServiceApiException
     */
    public function uploadItemContent(string $filePath, string $content, string $tenantId): void
    {
        if (!$this->featureFlagAdapter->isEnabled($tenantId, self::ITEM_CONTENT_UPLOAD_ENABLED)) {
            $this->logger->info(
                sprintf(
                    'Skipping upload of content to the ContentService for item %s as the feature flag is disabled',
                    $filePath,
                ),
            );
            return;
        }

        if (!$this->contentServiceApiUrl) {
            throw new ContentServiceApiException('ContentService API URL is not set');
        }

        $this->logger->info(sprintf('Uploading content to the ContentService for item %s', $filePath));
        try {
            // Use the file as the body in the request with data-binary
            $response = $this->client->request(
                'POST',
                sprintf('%s/api/v1/upload/%s/%s', $this->contentServiceApiUrl, $tenantId, $filePath),
                [
                    'headers' => [
                        'x-userid' => 'admin',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $content, // Send the JSON-encoded string directly as binary
                ],
            );
        } catch (Exception $exception) {
            throw new ContentServiceApiException(
                sprintf(
                    'Failed to upload content to the ContentService for item %s. Error: %s',
                    $filePath,
                    $exception->getMessage(),
                ),
                $exception->getCode(),
                $exception,
            );
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new ContentServiceApiException(
                sprintf(
                    'Failed to upload content to the ContentService for item %s. Status code: %d. Response: %s',
                    $filePath,
                    $response->getStatusCode(),
                    $response->getContent(false),
                ),
            );
        }
        $this->logger->info(sprintf('Content uploaded to the ContentService for item %s', $filePath));
    }
}
