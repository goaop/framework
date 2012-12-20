<?php

namespace Go\Tests;

class First
{

    private $private = T_PRIVATE;
    protected $protected = T_PROTECTED;
    public $public = T_PUBLIC;

    private static $staticPrivate = T_PRIVATE;
    protected static $staticProtected = T_PROTECTED;
    protected static $staticPublic = T_PUBLIC;

    // Dynamic methods that access $this-> properties
    private function privateMethod()
    {
        return $this->private;
    }

    protected function protectedMethod()
    {
        return $this->protected;
    }

    public function publicMethod()
    {
        return $this->public;
    }

    // Static methods that access self:: properties
    private static function staticSelfPrivate()
    {
        return self::$staticPrivate;
    }

    protected static function staticSelfProtected()
    {
        return self::$staticProtected;
    }

    public static function staticSelfPublic()
    {
        return self::$staticPublic;
    }

    public static function staticSelfPublicAccessPrivate()
    {
        return self::$staticPrivate;
    }

    // Static methods that access static:: properties with LSB
    protected static function staticLsbProtected()
    {
        return get_called_class();
    }

    public static function staticLsbPublic()
    {
        return get_called_class();
    }
}
