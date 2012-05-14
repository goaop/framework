<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

/**
 * General object for framework
 *
 * @package go
 * @subpackage core
 */
class Object
{
    /** @var array Configuration for this instance */
    protected $_config = array();

    function __construct(array $config = array())
    {
        $classConfig   = static::getClassConfig();
        $this->_config = array_replace_recursive($classConfig, $config);
        if ($this->_config['init']) {
            $this->init();
        }
    }

    /**
     * Returns general configuration for current class
     *
     * @return array
     */
    protected static function getClassConfig()
    {
        return array('init' => true);
    }

    /**
     * Returns list of fields which values will be initialized from config
     *
     * @return array
     */
    protected static function getAutoConfigFields()
    {
        return array();
    }

    /**
     * Initialization of current object
     *
     * This method will be called if value for "init" key in the config is not false.
     *
     * @return void
     */
    protected function init()
    {
        foreach (static::getAutoConfigFields() as $fieldName) {
            $this->$fieldName = $this->_config[$fieldName];
        }
    }
}
