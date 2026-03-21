<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use LogicException;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\UnionType;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

readonly class ReflectionTypeToASTTypeGenerator
{
    public function __construct(
        private ReflectionClass $context
    ) {}

    public function generate(ReflectionType|null $reflectionType): Name|IntersectionType|Identifier|UnionType|null
    {
        switch (true) {
            case $reflectionType instanceof ReflectionUnionType:
                $mappedTypes = [];
                foreach ($reflectionType->getTypes() as $singleType) {
                    $mappedTypes[] = $this->generate($singleType);
                }
                return new UnionType($mappedTypes);

            case $reflectionType instanceof ReflectionIntersectionType:
                $mappedTypes = [];
                foreach ($reflectionType->getTypes() as $singleType) {
                    $mappedTypes[] = $this->generate($singleType);
                }
                return new IntersectionType($mappedTypes);

            case $reflectionType instanceof ReflectionNamedType:
                $typeName = $reflectionType->getName();
                $nullablePrefix = $reflectionType->allowsNull() && !in_array($typeName, ['null', 'mixed']) ? '?' : '';
                if ($reflectionType->isBuiltin()) {
                    return new Identifier($nullablePrefix . $typeName);
                } elseif ($typeName === 'self') {
                    return new Name($nullablePrefix . '\\' . $this->context->getName());
                } elseif ($typeName === 'static') {
                    return new Name($nullablePrefix . $typeName);
                } else {
                    return new Name($nullablePrefix . '\\' . $typeName);
                }

            case $reflectionType === null:
                return null;

            default:
                throw new LogicException('Unsupported type "' . $reflectionType::class . '"');
        }
    }
}