<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\core;

/**
 * Base Configuration class
 *
 * @package go
 * @subpackage core
 */
class Config
{
    /** @var boolean Whether or not to call init() for object, set to false in configuration to disable */
    public $init = true;

    /** @var mixed Configuration for fields for object*/
    public $object = array();

    function __construct(array $config)
    {
        $defaultConfiguration = get_class_vars(__CLASS__);
        $configuration = array_intersect_key($config, $defaultConfiguration) + $defaultConfiguration;
        foreach ($configuration as $field => $value) {
            $this->$field = $value;
        }
    }

}
