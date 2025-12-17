<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\DeepLinking\Builder;

use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\Delivery;
use App\Lti\DeepLinking\Builder\ResourceCollectionBuilder;
use App\Service\ApplicationInfoService;
use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class ResourceCollectionBuilderTest extends TestCase
{
    private ResourceCollectionBuilder $subject;
    private RouterInterface|MockObject $routerMock;
    private ApplicationInfoService|MockObject $applicationInfoServiceMock;

    protected function setUp(): void
    {
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->applicationInfoServiceMock = $this->createMock(ApplicationInfoService::class);

        $this->subject = new ResourceCollectionBuilder(
            $this->routerMock,
            $this->applicationInfoServiceMock,
        );
    }

    public function testInit(): void
    {
        $this->assertSame([], $this->subject->getResourceCollection()->normalize());
    }

    public function testWithBatteries(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->exactly(2))
            ->method('getBackendUrl')
            ->willReturn('https://backend.url');

        $this->routerMock
            ->expects($this->exactly(2))
            ->method('generate')
            ->withConsecutive(
                ['api_v1_launch_lti_1p3_battery', ['batteryId' => 'id1']],
                ['api_v1_launch_lti_1p3_battery', ['batteryId' => 'id2']],
            )
            ->willReturnOnConsecutiveCalls(
                '/battery/id1',
                '/battery/id2',
            );


        $resourceCollection = $this->subject
            ->withBatteries(...$this->getTestBatteries())
            ->getResourceCollection();

        $this->assertSame([
            [
                'title' => 'name 1',
                'url' => 'https://backend.url/battery/id1',
                'type' => 'ltiResourceLink',
            ],
            [
                'title' => 'name 2',
                'url' => 'https://backend.url/battery/id2',
                'type' => 'ltiResourceLink',
            ],
        ], $resourceCollection->normalize());
    }

    public function testWithDeliveries(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->exactly(2))
            ->method('getBackendUrl')
            ->willReturn('https://backend.url');

        $this->routerMock
            ->expects($this->exactly(2))
            ->method('generate')
            ->withConsecutive(
                ['api_v1_launch_lti_1p3', ['deliveryId' => 'id1']],
                ['api_v1_launch_lti_1p3', ['deliveryId' => 'id2']],
            )
            ->willReturnOnConsecutiveCalls(
                '/delivery/id1',
                '/delivery/id2',
            );

        $resourceCollection = $this->subject
            ->withDeliveries(...$this->getTestDeliveries())
            ->getResourceCollection();

        $this->assertSame([
            [
                'title' => 'Delivery Label #1',
                'url' => 'https://backend.url/delivery/id1',
                'type' => 'ltiResourceLink',
            ],
            [
                'title' => 'undefined',
                'url' => 'https://backend.url/delivery/id2',
                'type' => 'ltiResourceLink',
            ],
        ], $resourceCollection->normalize());
    }

    public function testReset(): void
    {
        $this->applicationInfoServiceMock
            ->expects($this->exactly(2))
            ->method('getBackendUrl')
            ->willReturn('https://backend.url');

        $this->routerMock
            ->expects($this->exactly(2))
            ->method('generate')
            ->withConsecutive(
                ['api_v1_launch_lti_1p3_battery', ['batteryId' => 'id1']],
                ['api_v1_launch_lti_1p3_battery', ['batteryId' => 'id2']],
            )
            ->willReturnOnConsecutiveCalls(
                '/battery/id1',
                '/battery/id2',
            );

        $resourceCollection = $this->subject
            ->withBatteries(...$this->getTestBatteries())
            ->reset()
            ->getResourceCollection();

        $this->assertSame([], $resourceCollection->normalize());
    }

    private function getTestBatteries(): array
    {
        return [
            new Battery(
                'id1',
                'name 1',
                'description',
                'mode',
                'status',
                'tenantId',
                ['deliveryId1', 'deliveryId2'],
            ),
            new Battery(
                'id2',
                'name 2',
                'description',
                'mode',
                'status',
                'tenantId',
                ['deliveryId1', 'deliveryId2'],
            ),
        ];
    }

    private function getTestDeliveries(): array
    {
        return [
            new Delivery(
                'id1',
                ['qtiItemsMapping'],
                'tenantId',
                'compactTestFilePath',
                ['label' => 'Delivery Label #1'],
                new DateTime(),
            ),
            new Delivery(
                'id2',
                ['qtiItemsMapping'],
                'tenantId',
                'compactTestFilePath',
                ['foo' => 'bar'],
                new DateTime(),
            ),
        ];
    }
}
