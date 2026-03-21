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

use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Stmt\Expression;

class ParentConstructorCallASTGenerator extends AbstractFunctionLikeInvocationCallASTGenerator
{
    public function generate(): Expression
    {
        assert($this->functionLike instanceof \ReflectionMethod, 'Only valid methods are allowed');
        assert($this->functionLike->isConstructor(), 'Only valid constructors are allowed');

        $builder      = new BuilderFactory();
        $argumentList = $this->generateInvocationArgumentList(withContext: false);
        if ($this->functionLike->getNumberOfParameters() > 0) {
            $argumentList = array_map(fn($arg): Arg => new Arg($arg, unpack: true), $argumentList);
        }
        $methodCallExpression = $builder->staticCall('parent', '__construct', $argumentList);

        return new Expression($methodCallExpression);
    }
}