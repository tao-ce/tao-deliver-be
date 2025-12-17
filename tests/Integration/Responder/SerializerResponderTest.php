<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Responder;

use App\Responder\SerializerResponder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Exception;
use Throwable;

class SerializerResponderTest extends KernelTestCase
{
    public function testCreateJsonResponse(): void
    {
        $data = ['some' => 'data'];

        $response = $this->prepareResponderInstance()->createJsonResponse($data);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(json_encode($data), $response->getContent());
    }

    public function testCreateCustomJsonResponse(): void
    {
        $data = ['some' => 'data'];

        $response = $this->prepareResponderInstance()->createJsonResponse(
            $data,
            Response::HTTP_CREATED,
            ['some' => 'header'],
        );

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertEquals('header', $response->headers->get('some'));
        $this->assertEquals(json_encode($data), $response->getContent());
    }

    public function testCreateErrorJsonResponseWithoutDebug(): void
    {
        $exception = new Exception('custom error message');

        $response = $this->prepareResponderInstance()->createErrorJsonResponse($exception);

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertEquals('custom error message', $decodedResponse['error']['message']);
        $this->assertArrayNotHasKey('trace', $decodedResponse['error']);
    }

    public function testCreateCustomErrorJsonResponseWithDebug(): void
    {
        $exception = new Exception('custom error message');

        $response = $this->prepareResponderInstance(true)->createErrorJsonResponse($exception);

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertEquals('custom error message', $decodedResponse['error']['message']);
        $this->assertArrayHasKey('trace', $decodedResponse['error']);
    }

    public function testCreateHttpErrorJsonResponseWithoutDebug(): void
    {
        $exception = $this->createHttpException('http custom error message', Response::HTTP_I_AM_A_TEAPOT);

        $response = $this->prepareResponderInstance()->createErrorJsonResponse($exception);

        $this->assertEquals(Response::HTTP_I_AM_A_TEAPOT, $response->getStatusCode());
        $this->assertEquals('exceptionHeader', $response->headers->get('some'));

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertEquals('http custom error message', $decodedResponse['error']['message']);
        $this->assertArrayNotHasKey('trace', $decodedResponse['error']);
    }

    public function testCreateHttpErrorJsonResponseWithDebug(): void
    {
        $exception = $this->createHttpException('http custom error message', Response::HTTP_I_AM_A_TEAPOT);

        $response = $this->prepareResponderInstance(true)->createErrorJsonResponse($exception);

        $this->assertEquals(Response::HTTP_I_AM_A_TEAPOT, $response->getStatusCode());
        $this->assertEquals('exceptionHeader', $response->headers->get('some'));

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertEquals('http custom error message', $decodedResponse['error']['message']);
        $this->assertArrayHasKey('trace', $decodedResponse['error']);
    }

    private function prepareResponderInstance(bool $debug = false): SerializerResponder
    {
        $_ENV['APP_DEBUG'] = $debug;

        static::bootKernel();

        /** @var SerializerResponder $responder*/
        $responder = static::getContainer()->get(SerializerResponder::class);

        return $responder;
    }

    private function createHttpException(string $message, int $statusCode): Throwable
    {
        return new class ($message, $statusCode) extends Exception implements HttpExceptionInterface {
            public function getStatusCode(): int
            {
                return $this->code;
            }

            public function getHeaders(): array
            {
                return ['some' => 'exceptionHeader'];
            }
        };
    }
}
