<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    League\FlysystemBundle\FlysystemBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Nelmio\CorsBundle\NelmioCorsBundle::class => ['all' => true],
    OAT\Bundle\DocumentManagerBundle\DocumentManagerBundle::class => ['all' => true],
    OAT\Bundle\ElasticsearchDocumentManagerBundle\ElasticsearchDocumentManagerBundle::class => ['all' => true],
    OAT\Bundle\QtiBundle\QtiBundle::class => ['all' => true],
    OAT\Bundle\FilesystemDocumentManagerBundle\FilesystemDocumentManagerBundle::class => ['all' => true],
    OAT\Bundle\BigtableDocumentManagerBundle\BigtableDocumentManagerBundle::class => ['prod' => true, 'worker' => true],
    OAT\Bundle\CacheDocumentManagerBundle\CacheDocumentManagerBundle::class => ['all' => true],
    OAT\Bundle\Lti1p3Bundle\Lti1p3Bundle::class => ['all' => true],
    OAT\Bundle\HealthCheckBundle\HealthCheckBundle::class => ['all' => true],
    OAT\Bundle\EnvironmentManagementClientBundle\EnvironmentManagementClientBundle::class => ['all' => true],
    PetitPress\GpsMessengerBundle\GpsMessengerBundle::class => ['all' => true],
    OAT\Bundle\GraderMessengerBridgeBundle\GraderMessengerBridgeBundle::class => ['all' => true],
];
