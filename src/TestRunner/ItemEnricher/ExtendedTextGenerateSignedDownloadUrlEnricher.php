<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Generator\Attachment\AttachmentUrlGenerator;
use App\TestRunner\ItemEnricher\Contract\ItemStateEnricherInterface;
use RuntimeException;

class ExtendedTextGenerateSignedDownloadUrlEnricher implements ItemStateEnricherInterface
{
    private const GET_IMG_ID_REGEX = '/(<img[^>]*data-img-id=[^"]*"([^"]+)"[^>]*)/is';

    public function __construct(private AttachmentUrlGenerator $urlGenerator)
    {
    }

    /**
     * return modified ItemState
     */
    public function enrich(mixed $responseVariable): mixed
    {
        return $this->updateResponseVariable($responseVariable);
    }

    private function updateResponseVariable(&$responseVariable): mixed
    {
        if (is_array($responseVariable)) {
            if (
                !empty($responseVariable['base']['string'])
            ) {
                if (preg_match(self::GET_IMG_ID_REGEX, $responseVariable['base']['string'])) {
                    $responseVariable['base']['string'] = $this->parseImgTagAndReplaceToUrl($responseVariable['base']['string']);
                }
            } else {
                array_walk(
                    $responseVariable,
                    [$this, 'updateResponseVariable'],
                );
            }
        }

        return $responseVariable;
    }

    private function parseImgTagAndReplaceToUrl(string $input): string
    {
        preg_match_all(self::GET_IMG_ID_REGEX, $input, $expRes);

        [, $imgHtmlList, $imgIdList] = $expRes;
        if (empty($imgIdList)) {
            return $input;
        }

        foreach ($imgIdList as $pos => $imgId) {
            if (empty($imgId)) {
                throw new RuntimeException('[data-img-id] Attribute required for generate img download  url');
            }

            $url = $this->urlGenerator->generateDownloadUrl(rtrim($imgId, '\\'));

            $commonReplacePattern = '/(src=[^"]*")[^"\\\]+(")/';
            $commonReplaceString = sprintf(
                '$1%s$2',
                $url,
            );

            if (!str_contains($imgHtmlList[$pos], 'src=')) {
                $commonReplacePattern = '/(data-img-id=[^"]*"[^"\\\]+")/';
                $commonReplaceString = sprintf(
                    'src="%s" $1',
                    $url,
                );
            }

            $input = str_replace(
                $imgHtmlList[$pos],
                preg_replace(
                    $commonReplacePattern,
                    $commonReplaceString,
                    $imgHtmlList[$pos],
                ),
                $input,
            );
        }
        return $input;
    }
}
