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

use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt\Property;

/**
 * Prepares the definition for joinpoints private property in the class
 */
final class JoinPointPropertyASTGenerator
{
    /**
     * Default property name for storing join points in the class
     */
    public const PROPERTY_NAME = '__joinPoints';

    /**
     * JoinPointProperty generator
     *
     * @param array $adviceNames List of advices to apply per class
     */
    public function generate(array $adviceNames): Property
    {
        $builder = new BuilderFactory();

        $joinPointProperty = $builder->property(self::PROPERTY_NAME);
        $joinPointProperty
            ->makePrivate()
            ->makeStatic()
        ;
        $joinPointProperty
            ->setType('array')
            ->setDefault($adviceNames)
        ;

        $joinPointProperty->setDocComment(<<<DOCBLOCK
        /**
         * List of applied advices per class.
         * @internal
         */
        DOCBLOCK);

        return $joinPointProperty->getNode();
    }
}
