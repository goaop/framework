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

use Go\Aop\Intercept\Joinpoint;
use Laminas\Code\Generator\DocBlock\Tag\VarTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\Exception\InvalidArgumentException;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\PropertyValueGenerator;
use Laminas\Code\Generator\TypeGenerator;

/**
 * Prepares the definition for joinpoints private property in the class
 */
final class JoinPointPropertyGenerator extends PropertyGenerator
{
    /**
     * Default property name for storing join points in the class
     */
    public const NAME = '__joinPoints';

    /**
     * JoinPointProperty constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        $value = new PropertyValueGenerator([], PropertyValueGenerator::TYPE_ARRAY_SHORT);

        parent::__construct(self::NAME, $value, PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);

        $this->setType(TypeGenerator::fromTypeString('array'));

        $docBlock = new DocBlockGenerator('List of applied advices per class');
        $docBlock->setTag(new VarTag(null, 'array<string, \\' . Joinpoint::class . '>'));
        $this->setDocBlock($docBlock);
    }
}
