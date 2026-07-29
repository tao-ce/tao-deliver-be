<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Validator\DeliveryExecution;

use App\Service\Lti\LtiTokenResolverInterface;
use App\Validator\DeliveryExecution\SetAnnotationCommentActionValidator;
use App\Validator\Exception\RequestValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SetAnnotationCommentActionValidatorTest extends TestCase
{
    private LtiTokenResolverInterface&MockObject $ltiTokenResolver;
    private ValidatorInterface&MockObject $validator;
    private SetAnnotationCommentActionValidator $sut;

    protected function setUp(): void
    {
        $this->ltiTokenResolver = $this->createMock(LtiTokenResolverInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->sut = new SetAnnotationCommentActionValidator(
            $this->ltiTokenResolver,
            $this->validator,
        );
    }

    public function testValidateTokenRolesDelegatesToResolver(): void
    {
        $this->ltiTokenResolver->expects($this->once())
            ->method('hasOneOfRoles')
            ->with([LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR])
            ->willReturn(true);

        $this->assertTrue($this->sut->validateTokenRoles());
    }

    public function testValidateTokenRolesReturnsFalseOnFailure(): void
    {
        $this->ltiTokenResolver->expects($this->once())
            ->method('hasOneOfRoles')
            ->willReturn(false);

        $this->assertFalse($this->sut->validateTokenRoles());
    }

    public function testGetRequestDataExtractsJsonContent(): void
    {
        $payload = ['itemId' => '123', 'annotations' => []];
        $request = new Request([], [], [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');

        $method = new ReflectionMethod($this->sut, 'getRequestData');

        $result = $method->invoke($this->sut, $request);

        $this->assertSame($payload, $result);
    }

    public function testGetRequestValidationConstraintStructure(): void
    {
        $method = new ReflectionMethod($this->sut, 'getRequestValidationConstraint');

        $constraints = $method->invoke($this->sut);

        $this->assertIsArray($constraints);

        $collectionConstraint = $this->findConstraintInstanceOf($constraints, Collection::class);
        $this->assertNotNull($collectionConstraint, 'Main Collection constraint not found');

        $itemIdRaw = $collectionConstraint->fields['itemId'] ?? [];
        $itemIdConstraints = is_array($itemIdRaw) ? $itemIdRaw : [$itemIdRaw];

        $this->assertConstraintExists($itemIdConstraints, NotBlank::class, 'itemId');
        $this->assertConstraintExists($itemIdConstraints, Required::class, 'itemId');
        $this->assertConstraintExists($itemIdConstraints, Type::class, 'itemId');

        $typeConstraint = $this->findConstraintInstanceOf($itemIdConstraints, Type::class);
        $this->assertSame('string', $typeConstraint->type);

        $annotationsRaw = $collectionConstraint->fields['annotations'] ?? [];
        $annotationsConstraints = is_array($annotationsRaw) ? $annotationsRaw : [$annotationsRaw];

        $this->assertConstraintExists($annotationsConstraints, Required::class, 'annotations');
        $this->assertConstraintExists($annotationsConstraints, Type::class, 'annotations');

        $annotationsTypeConstraint = $this->findConstraintInstanceOf($annotationsConstraints, Type::class);
        $this->assertSame('array', $annotationsTypeConstraint->type);
    }

    /**
     * @test
     * @see AbstractRequestValidator::extractRequestJsonContent
     */
    public function testGetRequestDataThrowsExceptionOnMalformedJson(): void
    {
        // 1. Create a request with invalid JSON syntax (missing closing brace)
        $malformedPayload = '{"itemId": "123", "annotations": []';
        $request = new Request([], [], [], [], [], [], $malformedPayload);
        $request->headers->set('Content-Type', 'application/json');

        $method = new ReflectionMethod($this->sut, 'getRequestData');

        // 2. Expect your custom application exception
        $this->expectException(RequestValidationException::class);
        $this->expectExceptionMessage('Invalid JSON request body received');

        // 3. Execution
        $method->invoke($this->sut, $request);
    }

    /**
     * Recursively searches for a constraint instance of a specific class.
     * Handles nested Composite constraints (checks both $constraints and nested options).
     * * @param Constraint[] $constraints
     */
    private function findConstraintInstanceOf(array $constraints, string $class): ?Constraint
    {
        foreach ($constraints as $constraint) {
            // 1. Check the constraint itself
            if ($constraint instanceof $class) {
                return $constraint;
            }

            // 2. Check if it's a Composite (like Required) that holds others in ->constraints
            if ($constraint instanceof Composite) {
                // Standard Symfony Composite property
                if (!empty($constraint->constraints)) {
                    $found = $this->findConstraintInstanceOf($constraint->constraints, $class);
                    if ($found) {
                        return $found;
                    }
                }

                // Fallback: check nestedConstraints (older versions) or options array
                if (property_exists($constraint, 'nestedConstraints') && !empty($constraint->nestedConstraints)) {
                    $found = $this->findConstraintInstanceOf($constraint->nestedConstraints, $class);
                    if ($found) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    private function assertConstraintExists(array $constraints, string $class, string $fieldName): void
    {
        $found = $this->findConstraintInstanceOf($constraints, $class);

        // Build a debug string of found classes to help if it fails
        $foundClasses = $this->dumpConstraintClasses($constraints);

        $this->assertNotNull($found, sprintf(
            "Expected constraint '%s' not found for field '%s'. Found constraints structure: [%s]",
            $class,
            $fieldName,
            $foundClasses,
        ));
    }

    private function dumpConstraintClasses(array $constraints): string
    {
        $names = [];
        foreach ($constraints as $c) {
            $name = get_class($c);
            if ($c instanceof Composite && !empty($c->constraints)) {
                $name .= '(' . $this->dumpConstraintClasses($c->constraints) . ')';
            }
            $names[] = $name;
        }
        return implode(', ', $names);
    }
}
