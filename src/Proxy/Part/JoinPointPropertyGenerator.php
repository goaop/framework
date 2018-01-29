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

use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Generator\PropertyValueGenerator;

/**
 * Prepares the definition for joinpoints private property in the class
 */
final class JoinPointPropertyGenerator extends PropertyGenerator
{
    /**
     * Default property name for storing join points in the class
     */
    const NAME = '__joinPoints';

    /**
     * JoinPointProperty constructor.
     *
     * @param array $advices List of advices to apply per class
     *
     * @throws \Zend\Code\Generator\Exception\InvalidArgumentException
     */
    public function __construct(array $advices)
    {
        $value = new PropertyValueGenerator($advices, PropertyValueGenerator::TYPE_ARRAY_SHORT);

        parent::__construct(self::NAME, $value, PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);

        $this->setDocBlock('List of applied advices per class');
    }
}
