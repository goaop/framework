<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use Go\Aop\Intercept\MethodInvocation;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\PropertyGenerator;
use Go\Proxy\Generator\PropertyNodeProvider;
use Go\Proxy\Generator\TypeGenerator;
use Go\Proxy\Generator\ValueGenerator;
use PhpParser\Node\Stmt\Property as PropertyNode;

/**
 * Prepares the definition for joinpoints private property in the class
 */
final class JoinPointPropertyGenerator implements PropertyNodeProvider
{
    /**
     * Default property name for storing join points in the class
     */
    public const NAME = '__joinPoints';

    private PropertyGenerator $generator;

    public function __construct()
    {
        $this->generator = new PropertyGenerator(
            self::NAME,
            [],
            PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC
        );

        $this->generator->setType(TypeGenerator::fromTypeString('array'));

        $docBlock = new DocBlockGenerator(
            'List of applied advices per class',
            implode("\n", [
                'Typed as MethodInvocation because generated method bodies (method:* and static:* keys)',
                'call ->__invoke() directly. Other joinpoint types stored here use explicit casts:',
                '  - prop:*        ClassFieldAccess — cast in PropertyInterceptionTrait',
                '  - staticinit:*  StaticInitializationJoinpoint — instanceof check in ClassProxyGenerator::injectJoinPoints()',
                '  - init:*        ReflectionConstructorInvocation — accessed via ConstructorExecutionTransformer',
            ])
        );
        $docBlock->addTag('var', 'array<string, \\' . MethodInvocation::class . '>');
        $this->generator->setDocBlock($docBlock);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function generate(): string
    {
        return $this->generator->generate();
    }

    public function getNode(): PropertyNode
    {
        return $this->generator->getNode();
    }
}
