<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Publication\Model\Publication;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;

/**
 * @method Publication find(string $documentId)
 */
class PublicationRepository extends DocumentRepository
{
    public function __construct(DocumentManagerInterface $manager)
    {
        parent::__construct($manager, Publication::class);
    }
}
