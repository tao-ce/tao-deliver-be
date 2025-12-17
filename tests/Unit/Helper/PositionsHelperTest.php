<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\PositionsHelper;
use PHPUnit\Framework\TestCase;
use qtism\data\TestPartCollection;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\Route;
use qtism\runtime\tests\RouteItem;
use qtism\data\ExtendedAssessmentItemRef;
use qtism\data\AssessmentSection;
use qtism\data\TestPart;
use qtism\data\AssessmentTest;
use qtism\data\state\ResponseDeclarationCollection;
use qtism\common\collections\IdentifierCollection;
use qtism\runtime\tests\RouteItemCollection;

class PositionsHelperTest extends TestCase
{
    public function testItReturnsEmptyPositionsDataGivenEmptyTestSession(): void
    {

        $expectedResult = [
            'informationalIndex' => 0,
            'item' => 0,
            'total' => 0,
        ];

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $result = PositionsHelper::getPositionData($testSessionMock);
        $this->assertEquals($expectedResult, $result);
    }

    public function testReturnsPositionsDataGivenTestSession(): void
    {
        $expectedResult = [
            'informationalIndex' => 0,
            'item' => 2,
            'total' => 2,
        ];

        // Mock dependencies
        $testSession = $this->createMock(AssessmentTestSession::class);
        $assesmentTest = $this->createMock(AssessmentTest::class);
        $route = $this->createMock(Route::class);
        $currentRouteItem = $this->createMock(RouteItem::class);
        $nextRouteItem = $this->createMock(RouteItem::class);
        $itemRef = $this->createMock(ExtendedAssessmentItemRef::class);
        $testPart = $this->createMock(TestPart::class);
        $anotherTestPart = $this->createMock(TestPart::class);

        // Configure mocks
        $testSession->method('getRoute')->willReturn($route);
        $testSession->method('getAssessmentTest')->willReturn($assesmentTest);

        $assesmentTest->method('getTestParts')->willReturn(new TestPartCollection([$testPart, $anotherTestPart]));

        $route->method('getPosition')->willReturn(1);
        $route->method('valid')->willReturn(true);
        $route->method('getRouteItemAt')->with(1)->willReturn($currentRouteItem);
        $route->method('getAllRouteItems')->willReturn(new RouteItemCollection([$currentRouteItem, $nextRouteItem]));

        $currentRouteItem->method('getAssessmentItemRef')->willReturn($itemRef);
        $currentRouteItem->method('getTestPart')->willReturn($testPart);

        $nextRouteItem->method('getAssessmentItemRef')->willReturn($itemRef);
        $nextRouteItem->method('getTestPart')->willReturn($anotherTestPart);

        $responseDeclarations = $this->createMock(ResponseDeclarationCollection::class);
        $itemRef->method('getResponseDeclarations')->willReturn($responseDeclarations);
        $responseDeclarations->method('getArrayCopy')->willReturn(['any']);
        $itemRef->method('getCategories')->willReturn(new IdentifierCollection([]));

        // Call the method
        $result = PositionsHelper::getPositionData($testSession);

        // Assertions
        $this->assertEquals($expectedResult, $result);
    }

    public function testReturnsPositionsDetailsGivenTestSession(): void
    {
        $expectedResult = [
            'section' => ['id' => 'section-id'],
            'item' => ['id' => 'item-id'],
            'part' => ['id' => 'part-id'],
        ];

        // Mock dependencies
        $testSession = $this->createMock(AssessmentTestSession::class);
        $route = $this->createMock(Route::class);
        $currentRouteItem = $this->createMock(RouteItem::class);
        $itemRef = $this->createMock(ExtendedAssessmentItemRef::class);
        $section = $this->createMock(AssessmentSection::class);
        $testPart = $this->createMock(TestPart::class);

        // Configure mocks
        $testSession->method('getRoute')->willReturn($route);
        $route->method('valid')->willReturn(true);
        $route->method('current')->willReturn($currentRouteItem);

        $currentRouteItem->method('getAssessmentItemRef')->willReturn($itemRef);
        $currentRouteItem->method('getAssessmentSection')->willReturn($section);
        $currentRouteItem->method('getTestPart')->willReturn($testPart);

        $itemRef->method('getIdentifier')->willReturn('item-id');
        $section->method('getIdentifier')->willReturn('section-id');
        $testPart->method('getIdentifier')->willReturn('part-id');

        // Call the method
        $result = PositionsHelper::getPositionDetails($testSession);

        // Assertions
        $this->assertIsArray($result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testIsItemInformationalReturnsTrueForInformationalItem()
    {
        $itemRef = $this->createMock(ExtendedAssessmentItemRef::class);
        $responseDeclarations = $this->createMock(ResponseDeclarationCollection::class);
        $itemRef->method('getResponseDeclarations')->willReturn($responseDeclarations);
        $itemRef->method('getCategories')->willReturn(new IdentifierCollection(['x-tao-itemusage-informational']));

        $result = PositionsHelper::isItemInformational($itemRef);

        $this->assertTrue($result);
    }

    public function testIsItemInformationalReturnsFalseForNonInformationalItem()
    {
        $itemRef = $this->createMock(ExtendedAssessmentItemRef::class);
        $responseDeclarations = $this->createMock(ResponseDeclarationCollection::class);
        $itemRef->method('getResponseDeclarations')->willReturn($responseDeclarations);
        $responseDeclarations->method('getArrayCopy')->willReturn(['any']);
        $itemRef->method('getCategories')->willReturn(new IdentifierCollection([]));

        $result = PositionsHelper::isItemInformational($itemRef);

        $this->assertFalse($result);
    }
}
