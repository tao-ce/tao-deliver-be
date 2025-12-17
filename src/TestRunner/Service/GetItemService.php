<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Cache\CacheTrait;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Generator\Asset\SignedUrlGeneratorInterface;
use App\Generator\UrlGenerator;
use App\ImageResponse\Service\ImageResponseReaderService;
use App\Lti\LtiCustomSettings;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Lti\LtiTokenResolverInterface;
use App\TestRunner\ItemEnricher\Contract\ItemEnricherInterface;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use Psr\Cache\CacheException;
use Psr\Log\LoggerInterface;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\pci\json\Marshaller;
use qtism\runtime\tests\AssessmentTestSessionState;
use stdClass;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;

class GetItemService
{
    use CacheTrait;

    public const DATA_TYPE_STATIC = 1;
    public const DATA_TYPE_DYNAMIC = 2;
    public const DATA_TYPE_BOTH = self::DATA_TYPE_STATIC | self::DATA_TYPE_DYNAMIC;

    public function __construct(
        private readonly FilesystemReader $qtiCompiledDeliveriesStorage,
        private readonly UrlGenerator $urlGenerator,
        private readonly SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry,
        protected CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly Marshaller $marshaller,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly GetItemDataService $itemDataService,
        private readonly ItemEnricherInterface $itemEnricher,
        private readonly LtiTokenResolverInterface $ltiTokenResolver,
        private readonly TestSessionInitiator $testSessionInitiator,
        private readonly ImageResponseReaderService $imageResponseReaderService,
    ) {
    }

    /**
     * @deprecated Use getItemStaticData or getItemDynamicData instead
     */
    public function getItem(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        $itemData = $this->getItemData($deliveryExecution, $itemIdentifier);
        $parameters = $deliveryExecution->getLtiLaunchParameters();
        return $deliveryExecution->isReview()
            ? $this->getItemReviewResponse(
                $deliveryExecution,
                $itemIdentifier,
                $parameters,
                $itemData,
            )
            : $this->getItemTestResponse($deliveryExecution, $itemIdentifier, self::DATA_TYPE_BOTH, $itemData);
    }

    public function getItemStaticData(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        /** TODO: $itemData should be remove when we found workaround for assets */
        $itemData = $this->getItemData($deliveryExecution, $itemIdentifier);
        return $this->getItemTestResponse($deliveryExecution, $itemIdentifier, self::DATA_TYPE_STATIC, $itemData);
    }

    public function getItemDynamicData(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        $parameters = $deliveryExecution->getLtiLaunchParameters();
        return $deliveryExecution->isReview()
            ? $this->getItemReviewResponse(
                $deliveryExecution,
                $itemIdentifier,
                $parameters,
                $this->getItemData($deliveryExecution, $itemIdentifier),
            )
            : $this->getItemTestResponse($deliveryExecution, $itemIdentifier, self::DATA_TYPE_DYNAMIC);
    }

    private function getExtraItemResponseData(
        DeliveryExecution $deliveryExecution,
        string $itemIdentifier,
        int $dataResponseType = self::DATA_TYPE_BOTH,
        array $itemData = [],
    ): array {
        $response = [];
        if ($dataResponseType & self::DATA_TYPE_DYNAMIC) {
            $response['itemState'] = $this->getItemStateResponse(
                $deliveryExecution->getExtraStateData()->getTemporaryItemState($itemIdentifier),
            );
        }

        if ($dataResponseType & self::DATA_TYPE_STATIC) {
            $response['portableElements'] = $this->getPortableElements($deliveryExecution, $itemIdentifier);
        }

        if (!empty($itemData)) {
            $itemData['enricherExtraData'] = [
                'dataResponseType' => $dataResponseType,
            ];
            $response['itemData'] = $this->itemEnricher->enrichData($deliveryExecution, $itemIdentifier, $itemData);
            unset($itemData['enricherExtraData']);
        }

        return $response;
    }


    private function getItemTestResponse(
        DeliveryExecution $deliveryExecution,
        string $itemIdentifier,
        int $dataResponseType = self::DATA_TYPE_BOTH,
        array $itemData = [],
    ): array {
        $this->auditDeliveryExecutionLogger->debug(
            sprintf(
                '[%s][GetItemService] - start creating response of item %s',
                $deliveryExecution->getId(),
                $itemIdentifier,
            ),
        );

        $response = [
            'baseUrl' => '',
            'itemIdentifier' => $itemIdentifier,
        ];

        $response = array_merge($response, $this->getExtraItemResponseData(
            $deliveryExecution,
            $itemIdentifier,
            $dataResponseType,
            $itemData,
        ));


        $this->auditDeliveryExecutionLogger->debug(
            sprintf(
                '[%s][GetItemService] - finish creating response of item %s',
                $deliveryExecution->getId(),
                $itemIdentifier,
            ),
        );

        return $response;
    }

    private function getItemReviewResponse(
        DeliveryExecution $deliveryExecution,
        string $itemIdentifier,
        array $parameters,
        array $itemData = [],
    ): array {
        $response = [];

        if ($this->ltiCustomSettings->isReviewModeWithCorrectAnswer($parameters)) {
            $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
            if ($testSession->getState() === AssessmentTestSessionState::INITIAL) {
                $this->testSessionInitiator->startQtiSession($deliveryExecution);
            }
            $itemSession = $testSession->getAssessmentItemSessions($itemIdentifier)->current();

            $correctResponse = [];

            foreach ($itemSession->getAllVariables() as $variable) {
                if (
                    $variable instanceof ResponseVariable
                    && !in_array($variable->getIdentifier(), ['duration', 'numAttempts'])
                ) {
                    $correctResponse[$variable->getIdentifier()] = $this->marshaller->marshall(
                        $variable->getCorrectResponse(),
                        Marshaller::MARSHALL_ARRAY,
                    );
                }
            }

            $response['correctResponse'] = empty($correctResponse) ? null : json_encode($correctResponse);

            $this->auditDeliveryExecutionLogger->debug(
                sprintf(
                    '[%s][GetItemService] - added correct answers for review mode to item %s',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );
        }

        $itemResponses = [];
        $itemState = $this->modifyItemState(
            $deliveryExecution->getExtraStateData()->getItemState($itemIdentifier),
        );
        if ($itemState && empty($parameters['is_anonymous'])) {
            foreach ($itemState as $responseVariableId => $responseVariable) {
                if (is_array($responseVariable) && array_key_exists('response', $responseVariable)) {
                    $itemResponses[$responseVariableId] = $responseVariable['response'];
                }
            }

            $this->auditDeliveryExecutionLogger->debug(
                sprintf(
                    '[%s][GetItemService] - added item response for review mode to item %s',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );
        }
        $itemState = $this->imageResponseReaderService->read($deliveryExecution, $itemIdentifier, $itemState);

        $extraData = $this->getExtraData($deliveryExecution, $itemIdentifier);
        $extraData['scoring']['comments']['inline'] = $this->getReviewInlineComment($deliveryExecution, $itemIdentifier);

        $reviewResponse = array_merge(
            $response,
            [
                'baseUrl' => '',
                'extraData' => $extraData,
                'itemIdentifier' => $itemIdentifier,
                'itemState' => json_encode($itemState ?: new stdClass()),
            ],
            $this->getExtraItemResponseData($deliveryExecution, $itemIdentifier, self::DATA_TYPE_STATIC, $itemData),
        );

        if (empty($parameters['is_anonymous'])) {
            $reviewResponse['itemResponse'] = empty($itemResponses) ? null : json_encode($itemResponses);
        }

        return $reviewResponse;
    }

    private function modifyItemState(?string $itemState): array
    {
        if ($itemState === null || $itemState === '{}') {
            return [];
        }
        $state = json_decode($itemState, true);
        if (json_last_error() || null === $state) {
            return [];
        }

        foreach ($state as $responseVariableId => $responseVariable) {
            $state[$responseVariableId] = $this->itemEnricher->enrichState($responseVariable);
        }

        return $state;
    }

    private function getPortableElements(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        $pciPath = $deliveryExecution->getPortableItemDataPath($itemIdentifier);
        $pciCacheKey = md5($pciPath);

        try {
            $portableElements = $this->getFromCache($pciCacheKey);

            $this->auditDeliveryExecutionLogger->debug(
                sprintf(
                    '[%s][GetItemService] - got PCI data %s from the cache',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );
        } catch (CacheException $exception) {
            $portableElements = null;
            $this->logger->error($exception->getMessage(), compact('exception'));
        }

        if (null === $portableElements) {
            $portableElementsJson = $this->qtiCompiledDeliveriesStorage->read($pciPath);

            $portableElements = json_decode(
                $portableElementsJson,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if ($portableElementsJson === false) {
                throw UnableToReadFile::fromLocation($pciPath);
            }

            try {
                $this->setInCache($pciCacheKey, $portableElements, TestSessionAccessorFactory::CACHE_DEFAULT_TTL);

                $this->auditDeliveryExecutionLogger->debug(
                    sprintf(
                        '[%s][GetItemService] - put PCI data %s in the cache',
                        $deliveryExecution->getId(),
                        $itemIdentifier,
                    ),
                );
            } catch (CacheException $exception) {
                $this->logger->error($exception->getMessage(), compact('exception'));
            }
        }

        $this->auditDeliveryExecutionLogger->debug(
            sprintf(
                '[%s][GetItemService] - got portable elements of item %s',
                $deliveryExecution->getId(),
                $itemIdentifier,
            ),
        );

        return $this->modifyPCIAssetsLinks($portableElements, $deliveryExecution->getId(), $itemIdentifier);
    }

    private function modifyPciAssetsLinks(
        array $portableElements,
        string $deliveryExecutionId,
        string $itemIdentifier,
    ): array {
        if (empty($portableElements['pci'])) {
            return $portableElements;
        }

        foreach ($portableElements['pci'] as &$content) {
            foreach ($content as &$element) {
                foreach ($element['runtime'] as &$data) {
                    $urlGenerator = $this->getSignedUrlGenerator(CloudCdnSignedUrlGenerator::NAME);
                    $data = is_array($data)
                        ? array_map(
                            fn($url) => $urlGenerator->generateDownloadUrl($url),
                            $data,
                        )
                        : $urlGenerator->generateDownloadUrl($data);
                }
            }
        }

        $this->auditDeliveryExecutionLogger->debug(
            sprintf(
                '[%s][GetItemService] - modified PCI assets links of item %s',
                $deliveryExecutionId,
                $itemIdentifier,
            ),
        );

        return $portableElements;
    }

    private function getSignedUrlGenerator(string $generatorName): SignedUrlGeneratorInterface
    {
        return $this->signedUrlGeneratorRegistry->getGenerator($generatorName);
    }


    private function getReviewInlineComment(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        if (
            !$this->ltiTokenResolver->hasOneOfRoles(
                [
                    LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR,
                    LtiTokenResolverInterface::LTI_ROLE_LEARNER,
                ],
            )
        ) {
            return [];
        }

        if (
            $this->ltiTokenResolver->hasOneOfRoles([ LtiTokenResolverInterface::LTI_ROLE_LEARNER])
            && !$deliveryExecution->isItemScoredExternally($itemIdentifier)
        ) {
            return [];
        }

        return $deliveryExecution->getReviewInlineComment()?->getFeedback($itemIdentifier) ?: [];
    }

    private function getExtraData(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        $extraData = [];
        $plagiarismReports = $deliveryExecution->getExtraStateData()->getPlagiarismReports();
        $responses = [];
        /** @var PlagiarismReport $report */
        foreach ($plagiarismReports as $report) {
            if (
                $report->getItemId() === $itemIdentifier
                && $this->isProviderEnabled($deliveryExecution, $report->getProvider())
            ) {
                $responses[$report->getProvider()]['provider'] = $report->getProvider();
                $responses[$report->getProvider()]['responses'][$report->getResponseId()] = [
                    'id' => $report->getId(),
                    'status' => $report->getStatus(),
                    'href' => $report->getHref(),
                    'reportUrl' => $this->urlGenerator->generate(
                        'api_v1_get_hbl_report',
                        [
                            'id' => $deliveryExecution->getId(),
                            'reportId' => $report->getId(),
                        ],
                        UrlGeneratorInterface::NETWORK_PATH,
                    ),
                ];
            }
        }

        if (!empty($responses)) {
            $extraData['plagiarismReports'] = array_values($responses);
        }

        if (
            $this->ltiTokenResolver->hasOneOfRoles(
                [
                    LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR,
                ],
            )
        ) {
            return $extraData;
        }

        $itemFeedback = $deliveryExecution->getItemOverallComment($itemIdentifier);
        if ($itemFeedback !== null) {
            $extraData['scoring']['comments']['overall'] = $itemFeedback;
        }

        return $extraData;
    }

    private function isProviderEnabled(DeliveryExecution $deliveryExecution, string $provider): bool
    {
        $tags = $this->ltiCustomSettings->getReviewExtraInfoTags($deliveryExecution->getLtiLaunchParameters()) ?? [];

        return in_array($provider, $tags, true);
    }

    private function getItemStateResponse(?string $itemState): ?string
    {
        $itemState = $this->modifyItemState($itemState);
        return $itemState ? json_encode($itemState) : null;
    }

    private function getItemData(DeliveryExecution $deliveryExecution, string $itemIdentifier): array
    {
        $itemData = $this->itemDataService->getItemDataByDeliveryExecution($itemIdentifier, $deliveryExecution);
        $this->auditDeliveryExecutionLogger->debug(
            sprintf(
                '[%s][GetItemService] - got item state of item %s',
                $deliveryExecution->getId(),
                $itemIdentifier,
            ),
        );

        // all data for feedback should come from itemSubmit
        if (!empty($itemData['data']['feedbacks'])) {
            unset($itemData['data']['feedbacks']);
        }

        return $itemData;
    }
}
