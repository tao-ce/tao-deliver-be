<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Attachments;

use App\Responder\SerializerResponder;
use League\Flysystem\FilesystemWriter;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UploadFileAction
{
    public function __construct(
        private readonly FilesystemWriter $deliveryExecutionUploadsStorage,
        private readonly SerializerResponder $responder,
    ) {
    }

    public function __invoke(Request $request, $path): JsonResponse
    {
        $file = $request->getContent();
        if (empty($file)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->deliveryExecutionUploadsStorage->write(
                $path,
                $file,
            );
            $success = true;
        } catch (UnableToWriteFile) {
            $success = false;
        }

        return $this->responder->createJsonResponse([
            'success' => $success,
        ]);
    }
}
