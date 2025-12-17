<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Generator;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\TestRunner\Normalizer\TimeConstraintNormalizer;
use League\Flysystem\FilesystemReader;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class TestContextGenerator
{
    public function __construct(
        private readonly FilesystemReader $qtiCompiledDeliveriesStorage,
        private readonly TimeConstraintNormalizer $timeConstraintNormalizer,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
        private readonly LtiCustomSettings $ltiCustomSettings,
    ) {
    }

    public function generate(
        AssessmentTestSession $testSession,
        DeliveryExecution $deliveryExecution,
        ?string $forcedItemId = null,
    ): array {
        $isTestSessionRunning = $testSession->isRunning();
        $currentRouteItem = $isTestSessionRunning
            ? $testSession->getRoute()->current()
            : null;

        $ltiParameters = $deliveryExecution->getLtiLaunchParameters();
        $isProctored = $this->ltiCustomSettings->isMonitoringEnabled($ltiParameters)
            || $this->ltiCustomSettings->getDeliverExecutionIdAlias($ltiParameters) !== null;

        return [
            'state' => $testSession->getState(),
            'status' => $deliveryExecution->getStatus(),
            'remainingAttempts' => $testSession->getCurrentRemainingAttempts(),
            'isTimeout' => $testSession->isTimeout(),
            'isProctored' => $isProctored,
            'itemIdentifier' => $forcedItemId
                ?: ($isTestSessionRunning ? $testSession->getCurrentAssessmentItemRef()->getIdentifier() : null),
            'attempt' => $isTestSessionRunning
                ? $testSession->getCurrentAssessmentItemSession()['numAttempts']->getValue()
                : null,
            'itemSessionState' => $isTestSessionRunning
                ? $testSession->getCurrentAssessmentItemSession()->getState()
                : null,
            'needMapUpdate' => false,
            'itemPosition' => $testSession->getRoute()->getPosition(),
            'timeConstraints' => $this->timeConstraintNormalizer->normalizeCollection($testSession),
            'testPartId' => $isTestSessionRunning ? $testSession->getCurrentTestPart()->getIdentifier() : null,
            'sectionId' => $isTestSessionRunning ? $testSession->getCurrentAssessmentSection()->getIdentifier() : null,
            'canMoveBackward' => $isTestSessionRunning ? $testSession->canMoveBackward() : null,
            'rubrics' => $isTestSessionRunning ? $this->getRubricBlocks($testSession, $deliveryExecution) : '',
            'allowSkipping' => $isTestSessionRunning
                ? $currentRouteItem->getItemSessionControl()->getItemSessionControl()->doesAllowSkipping()
                : null,
            'validateResponses' => $isTestSessionRunning
                ? $currentRouteItem->getItemSessionControl()->getItemSessionControl()->mustValidateResponses()
                : null,
        ];
    }

    private function getRubricBlocks(AssessmentTestSession $testSession, DeliveryExecution $deliveryExecution): string
    {
        $baseUrl = $this->requestStack->getCurrentRequest()
            ? $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost()
            : '';
        $rubricBlocksContent = '';
        $rubricBlocks = $testSession->getCurrentAssessmentSection()->getComponentsByClassName(
            'rubricBlockRef',
        );

        foreach ($rubricBlocks as $rubricBlock) {
            $file = $deliveryExecution->getAssetPath($rubricBlock->getHref());
            if ($this->qtiCompiledDeliveriesStorage->has($file)) {
                $rubricBlockContent = $this->qtiCompiledDeliveriesStorage->read($file);
                $template = $this->twig->createTemplate($rubricBlockContent);
                $rubricBlocksContent .= $template->render([
                    'baseUrl' => $baseUrl,
                    'testSession' => $testSession,
                ]);
            }
        }
        return trim($rubricBlocksContent);
    }
}
