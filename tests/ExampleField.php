<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Framework\FieldAroundInterceptor;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\FieldBeforeInterceptor;

class ExampleField extends Example
{
    /**
     * Original values of properties
     *
     * @var array
     */
    private $__properties = array();

    /**
     * Property that will be created in proxy automatically
     */
    private static $__joinpoints = array();

    /**
     * Proxy constructor example for fields interceptor
     */
    function __construct()
    {
        $this->__properties = array(
            'message' => $this->message
        );
        unset(
            $this->message
        );

        // TEST CODE HERE
        $advice = new FieldAroundInterceptor(function (FieldAccess $property) {
            $type = $property->getAccessType() === FieldAccess::READ ? 'read' : 'write';
            $value = $property->proceed();
            echo
                "Calling Around Interceptor for field: ",
                get_class($property->getThis()),
                "->",
                $property->getField()->getName(),
                ", access: $type",
                ", value: ",
                json_encode($value),
                "<br>\n";
            if ($property->getAccessType() === FieldAccess::WRITE) {
                return 'WRITE';
            }
            return $value;
        });

        self::$__joinpoints["prop:message"] = new ClassFieldAccess('Example', 'message', array($advice));
        // END OF TEST CODE
    }

    /**
     * Joinpoint get invoker
     *
     * @param string $name Name of the property
     *
     * @return mixed|null
     */
    function __get($name)
    {
        if (array_key_exists($name, $this->__properties)) {
            return self::$__joinpoints["prop:$name"]->__invoke(
                $this,
                FieldAccess::READ,
                $this->__properties[$name]
            );
        } elseif (method_exists(get_parent_class(), __FUNCTION__)) {
            return parent::__get($name);
        } else {
            trigger_error("Trying to access undeclared property {$name}");
            return null;
        }
    }

    /**
     * Joinpoint set invoker
     *
     * @param string $name Name of the property
     * @param mixed $value Value to set for property
     */
    function __set($name, $value)
    {
        if (array_key_exists($name, $this->__properties)) {
            $this->__properties[$name] = self::$__joinpoints["prop:$name"]->__invoke(
                $this,
                FieldAccess::WRITE,
                $this->__properties[$name],
                $value
            );
        } elseif (method_exists(get_parent_class(), __FUNCTION__)) {
            parent::__set($name, $value);
        } else {
            trigger_error("Trying to set undeclared property {$name}");
            $this->$name = $value;
        }
    }
}
