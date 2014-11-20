<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

/**
 * Transforms the source code to add an ability to intercept new instances creation
 *
 * @see https://github.com/php/php-src/blob/master/Zend/zend_language_parser.y
 *
 * new_expr:
 *   T_NEW
 *   class_name_reference
 *   ctor_arguments
 *
 * class_name_reference:
 *   class_name
 *   | new_variable
 *
 * class_name:
 *   T_STATIC
 *   | name
 *
 * namespace_name:
 *   T_STRING
 *   | namespace_name T_NS_SEPARATOR T_STRING
 *
 * name:
 *   namespace_name
 *   | T_NAMESPACE T_NS_SEPARATOR namespace_name
 *   | T_NS_SEPARATOR namespace_name
 *
 * ctor_arguments:
 *   / empty /
 *   | argument_list
 */
class ConstructorExecutionTransformer implements SourceTransformer
{

    /**
     * Singletone
     *
     * @return static
     */
    public static function getInstance()
    {
        static $instance = null;
        if (!$instance) {
            $instance = new static;
        }

        return $instance;
    }

    /**
     * Rewrites all "new" expressions with our implementation
     *
     * @param StreamMetaData $metadata Metadata for source
     *
     * @return void
     */
    public function transform(StreamMetaData $metadata = null)
    {
        if (strpos($metadata->source, 'new ')===false) {
            return;
        }

        $tokenStream       = token_get_all($metadata->source);
        $transformedSource = '';
        $isWaitingClass    = false;
        $isWaitingEnd      = false;
        $isClassName       = true;
        $classNamePosition = 0;
        foreach ($tokenStream as $index=>$token) {
            list ($token, $tokenValue) = (array) $token + array(1 => $token);
            if ($isWaitingClass && $token !== T_WHITESPACE && $token !== T_COMMENT) {
                $classNamePosition++;
                if ($token === T_VARIABLE && $classNamePosition === 1) {
                    $isWaitingEnd   = true;
                    $isWaitingClass = false;
                }
                if (in_array($tokenStream[$index+1], array('(', ';', ')', '.'))) {
                    $isWaitingEnd   = true;
                    $isWaitingClass = false;
                }
                if ($isClassName && $token !== T_NS_SEPARATOR && $token !== T_STRING && $token !== T_STATIC) {
                    $isClassName = false;
                }
            }
            if ($token === T_NEW) {
                $tokenValue = ' \\' . __CLASS__ . '::getInstance()->{';
                $isWaitingClass = true;
                $isClassName    = true;
                $classNamePosition = 0;
            }
            $transformedSource .= $tokenValue;

            if ($isWaitingEnd) {
                $transformedSource .= $isClassName ? '::class' : '';
                $transformedSource .= '}';
                $isWaitingEnd = false;
            }
        }
        $metadata->source = $transformedSource;
    }

    /**
     * Magic interceptor for instance creation
     *
     * @param string $className Name of the class to construct
     *
     * @return mixed
     */
    public function __get($className)
    {
        // TODO: Return from class invocation
        return new $className;
    }

    /**
     * Magic interceptor for instance creation
     *
     * @param string $className Name of the class to construct
     * @param array $args Arguments for the constructor
     *
     * @return mixed
     */
    public function __call($className, $args)
    {
        // TODO: Return from class invocation
        $refClass = new \ReflectionClass($className);
        $ctor     = $refClass->getConstructor();
        if ($ctor && $ctor->getNumberOfParameters()) {
            return $refClass->newInstanceArgs($args);
        }

        return $refClass->newInstance();
    }
}
