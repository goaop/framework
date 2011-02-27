<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\core;

/**
 * @package go
 * @subpackage core
 */
class Object
{
    /** @var Config Configuration for class */
    protected static $classConfig = null;

    function __construct($param = null)
    {
        $className = $this->getClass();
        if (empty(static::$classConfig[$className])) {
            static::$classConfig[$className] = new Config(static::getClassConfig($this));
        }
        foreach (static::$classConfig[$className]->object as $fieldName => $fieldValue) {
            $this->$fieldName = $fieldValue;
        }
        if (static::$classConfig[$className]->init !== false) {
            $this->init($param);
        }
    }

    public function getClass()
    {
        return get_called_class();
    }

    protected function init($param = null) {}

    /**
     * Returns general configuration for class
     *
     * @static
     * @param object $object
     * @return array
     */
    protected static function getClassConfig($object)
    {
        return array(
            'object' => static::getObjectConfig($object)
        );
    }

    /**
     * Returns default configuration for object
     *
     * @static
     * @param object $object
     * @return array
     */
    protected static function getObjectConfig($object)
    {
        return array();
    }
}
