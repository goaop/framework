<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty as Property;

use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

/**
 * AOP Factory that is used to generate child code from joinpoints
 */
class AopChildFactory extends AbstractChildCreator
{

    /**
     * Generates an child code by parent class reflection and joinpoints for it
     *
     * @param ReflectionClass|ParsedReflectionClass $parent Parent class reflection
     * @param array $joinpoints
     *
     * @return AopChildFactory
     */
    public static function generate($parent, array $joinpoints)
    {
        $aopChild = new static($parent, $parent->getShortName());
        if (!empty($joinpoints)) {
            $aopChild->addJoinpointsProperty($aopChild);

            foreach ($joinpoints as $name => $value) {

                list ($type, $name) = explode(':', $name);
                switch ($type) {
                    case 'method':
                        $aopChild->overrideMethod($parent->getMethod($name));
                        break;
                }
            }
        }
        return $aopChild;
    }

    /**
     * Adds a definition for joinpoints private property in the class
     *
     * @return void
     */
    protected function addJoinpointsProperty()
    {
        $this->setProperty(
            Property::IS_PRIVATE | Property::IS_STATIC,
            '__joinPoints',
            'array()'
        );
    }

    /**
     * Override parent method with joinjpoint invocation
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method Method reflection
     */
    protected function overrideMethod($method)
    {
        // temporary disable override of final methods
        if (!$method->isFinal()) {
            $this->override($method->getName(), $this->getJoinpointInvocationBody($method));
        }
    }

    /**
     * Creates definition for method body
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody($method)
    {
        $link = $method->isStatic() ? 'null' : '$this';
        $args = join(', ', array_map(function ($param) {
            return '$' . $param->getName();
        }, $method->getParameters()));

        $args = $link . ($args ? ", $args" : '');
        $body = "return self::\$__joinPoints['method:' . __FUNCTION__]->__invoke($args);";
        return $body;
    }
}
