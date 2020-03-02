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

use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\PropertyValueGenerator;

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
     * @param array $advices List of advices to apply per class
     *
     * @throws \Laminas\Code\Generator\Exception\InvalidArgumentException
     */
    public function __construct(array $advices)
    {
        $value = new PropertyValueGenerator($advices, PropertyValueGenerator::TYPE_ARRAY_SHORT);

        parent::__construct(self::NAME, $value, PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);

        $this->setDocBlock('List of applied advices per class');
    }
}
