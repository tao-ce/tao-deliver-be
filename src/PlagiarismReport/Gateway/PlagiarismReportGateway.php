<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\PlagiarismReport\Gateway;

use Exception;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class PlagiarismReportGateway
{
    private const ACCESS_TOKEN_CACHE_PREFIX = 'plagiarism-service-token';

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cacheChain,
        private ?string $reportUri,
        private ?string $accessTokenUri,
        private ?string $scope,
        private ?string $clientId,
        private ?string $clientSecret,
    ) {
    }

    public function getReport(string $id): array
    {
        if (!$this->reportUri || !$this->accessTokenUri || !$this->scope || !$this->clientId || !$this->clientSecret) {
            throw new ConflictHttpException('HBL gateway not configured');
        }

        try {
            $options = [
                'auth_bearer' => $this->getAccessToken(),
            ];

            $uri = sprintf('%s/%s', rtrim($this->reportUri, '/'), $id);
            $response = null;
            try {
                $response = $this->client->request('GET', $uri, $options);
                return $response->toArray();
            } catch (ClientExceptionInterface $exception) {
                if ($exception->getResponse()->getStatusCode() === 401) {
                    $options = [
                        'auth_bearer' => $this->getAccessToken(true),
                    ];

                    $response = $this->client->request('GET', $uri, $options);
                    return $response->toArray();
                }

                throw $exception;
            }
        } catch (DecodingExceptionInterface $exception) {
            $content = $response?->getContent();
            if (!$content) {
                throw $exception;
            }

            return ['reportUrl' => $content];
        } catch (Exception $exception) {
            throw new HttpException(
                424,
                $exception->getMessage(),
                $exception,
                code: $exception->getCode(),
            );
        }
    }

    private function getAccessToken(bool $forceRefresh = false): string
    {
        $item = $this->cacheChain->getItem(self::ACCESS_TOKEN_CACHE_PREFIX);

        if ($item->isHit()) {
            if (!$forceRefresh) {
                return $item->get();
            }

            $this->cacheChain->deleteItem(self::ACCESS_TOKEN_CACHE_PREFIX);
        }

        $response = $this->client->request('POST', $this->accessTokenUri, [
            'body' => [
                'grant_type' => 'client_credentials',
                'scope' => $this->scope,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $responseData = $response->toArray();

        $accessToken = $responseData['access_token'] ?? '';
        $expiresIn = $responseData['expires_in'] ?? '';

        if (empty($accessToken) || empty($expiresIn)) {
            throw new RuntimeException('invalid response body');
        }

        $item = $this->cacheChain->getItem(self::ACCESS_TOKEN_CACHE_PREFIX);

        $this->cacheChain->save(
            $item->set($accessToken)->expiresAfter($expiresIn),
        );

        return $accessToken;
    }
}
