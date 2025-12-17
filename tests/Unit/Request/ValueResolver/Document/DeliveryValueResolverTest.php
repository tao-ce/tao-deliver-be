<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Request\ValueResolver\Document;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use App\Request\ValueResolver\Document\DeliveryValueResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\EnvironmentManagementClient\Http\LtiMessageExtractorInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Security\Jwt\TokenInterface;
use OAT\Library\Lti1p3Core\Util\Collection\CollectionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeliveryValueResolverTest extends TestCase
{
    /** @var DeliveryValueResolver */
    private $subject;

    /** @var MockObject|DeliveryRepository */
    private $repository;

    /** @var MockObject|LtiMessageExtractorInterface */
    private $ltiMessageExtractor;

    protected function setUp(): void
    {
        $psr17Factory = new Psr17Factory();

        $this->repository = $this->createMock(DeliveryRepository::class);
        $this->ltiMessageExtractor = $this->createMock(LtiMessageExtractorInterface::class);
        $this->subject = new DeliveryValueResolver(
            $this->repository,
            $this->ltiMessageExtractor,
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
        );
    }

    public function testItCanResolveExistingDelivery(): void
    {
        $request = Request::create(
            'http://myurl',
            Request::METHOD_POST,
            ['id' => '1'],
        );

        $this->mockDelivery();

        $this->ltiMessageExtractor->method('extract')->willReturn($this->mockLtiMessage());

        $result = $this->subject->resolve($request, $this->createArgumentMetadata());
        $this->assertIsArray($result);

        /** @var DocumentInterface $documentFromAttributes */
        $documentFromAttributes = $result[0];

        $this->assertInstanceOf(DocumentInterface::class, $documentFromAttributes);
        $this->assertEquals('1', $documentFromAttributes->getId());
    }

    public function testItShouldThrowExceptionOnNotExistingDocument(): void
    {
        $this->repository
            ->method('find')
            ->willThrowException(new DocumentNotFoundException());

        $this->expectException(NotFoundHttpException::class);
        $this->subject->resolve(new Request([], []), $this->createArgumentMetadata());
    }

    public function testItShouldThrowExceptionIfDeliveryWasCreatedByAnotherTenant(): void
    {
        $this->mockDelivery();

        $this->expectException(AccessDeniedHttpException::class);
        $this->subject->resolve(
            Request::create(
                'http://myurl',
                Request::METHOD_GET,
            ),
            $this->createArgumentMetadata(),
        );
    }

    private function createArgumentMetadata(
        string $className = Delivery::class,
        string $name = 'document',
    ): ArgumentMetadata {
        $arguments = $this->createMock(ArgumentMetadata::class);
        $arguments->method('getType')->willReturn($className);
        $arguments->method('getName')->willReturn($name);

        return $arguments;
    }

    private function mockLtiMessage(): LtiMessagePayloadInterface
    {
        $ltiMsg = $this->createMock(LtiMessagePayloadInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $claims = $this->createMock(CollectionInterface::class);
        $claims->method('get')->willReturn('1');
        $token->method('getClaims')->willReturn($claims);
        $ltiMsg->method('getToken')->willReturn($token);

        return $ltiMsg;
    }

    private function mockDelivery(string $tenantId = '1'): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery
            ->method('getId')
            ->willReturn('1');
        $delivery
            ->method('getTenantId')
            ->willReturn($tenantId);

        $this->repository
            ->method('find')
            ->willReturn($delivery);
    }
}
