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

use Go\Core\AspectContainer;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;

class MethodInvocationCallASTGenerator extends AbstractFunctionLikeInvocationCallASTGenerator
{
    public function generate(): Expression|Return_
    {
        assert($this->functionLike instanceof \ReflectionMethod, 'Only valid methods are allowed');

        $builder  = new BuilderFactory();
        $isStatic = $this->functionLike->isStatic();
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        // self::$__joinPoints['method:name']
        $joinPointInstance = new ArrayDimFetch(
            var: new StaticPropertyFetch(new Name('self'), '__joinPoints'),
            dim: new String_($prefix . ':' . $this->functionLike->name)
        );

        // self::$__joinPoints['method:name']->__invoke(<arguments>);
        $methodCallExpression = $builder->methodCall(
            var: $joinPointInstance,
            name: '__invoke',
            args: $this->generateInvocationArgumentList()
        );

        // [return] self::$__joinPoints['method:name']->__invoke(<arguments>);
        return $this->wrapCallWithReturnIfNeeded($methodCallExpression);
    }
}