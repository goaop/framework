<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

use SplObjectStorage;

/**
 * Aspect class realization for PHP
 *
 * Aspect is a module that encapsulates a concern. An aspect is composed of pointcuts, advice bodies and inter-type
 * declarations. In some approaches, and aspect may also contain classes and methods.
 *
 * To define new aspect simply override getObjectConfig like this:
 *  class TestAspect extends go\aop\Aspect {
 *
 *      protected static function getObjectConfig($object)
 *      {
 *          return array(
 *              'pointcut' => go\aop\call('Test->test*'),
 *              'around' => function($params, go\aop\Joinpoint $joinPoint) {
 *                  echo "It' so easy to work with AOP";
 *                  return $joinPoint->proceed($params, $joinPoint);
 *              },
 *          );
 *      }
 *  }
 *
 * @link http://en.wikipedia.org/wiki/Aspect_%28computer_science%29
 * @package go
 * @subpackage aop
 */
class Aspect extends \go\core\Object {

    /**
     * Around advice(s) for aspect
     *
     * This public field contains definition of 'around' advices. It will be set from configuration for object.
     * @see \go\core\Object::getObjectConfig() for details of how to specify values for property
     * @var Advice|Advice[]
     */
    public $around = null;

    /**
     * Before advice(s) for aspect
     *
     * This public field contains definition of 'before' advices. It will be set from configuration for object.
     * @see \go\core\Object::getObjectConfig() for details of how to specify values for property
     * @var Advice|Advice[]
     */
    public $before = null;

    /**
     * After advice(s) for aspect
     *
     * This public field contains definition of 'after' advices. It will be set from configuration for object.
     * @see \go\core\Object::getObjectConfig() for details of how to specify values for property
     * @var Advice|Advice[]
     */
    public $after = null;

    /**
     * Pointcut(s) definition of joinpoints
     *
     * This property will be set from configuration for object.
     * @see \go\core\Object::getObjectConfig() for details of how to specify values for property
     * @var Pointcut|Pointcut[]
     */
    protected $pointcut = null;

    /**
     * Static mapping from pointcut object to aspect(s)
     *
     * @var array|\SplObjectStorage
     */
    protected static $aspects = array();

    /**
     * Static mapping from class name to associative array of joinpoints for this class
     *
     * @var array array[className][joinPointName] => joinPoint
     */
    protected static $joinPoints = array();

    /**
     * Returns list of fields which values will be initialized from config
     *
     * @return array
     */
    protected static function getAutoConfigFields()
    {
        return array('around', 'before', 'after', 'pointcut');
    }


    /**
     * Registers current aspect and enable advice processing for pointcut
     *
     * This function registers pointcut(s) defined in current aspect to global list of pointcuts and maps aspect to them.
     * After registering the aspect all AspectObject descendants can use advices defined in the current aspect. Single
     * pointcut instance can specify many aspects, so you can easily define pointcut's registry and use one instance of
     * pointcut in many aspects. This improve logic and increase speed of pointcut processing.
     *
     * @return void
     */
    final public function register()
    {
        self::$aspects = self::$aspects ?: new SplObjectStorage();
        $pointcuts = is_array($this->pointcut) ? $this->pointcut : array($this->pointcut);
        foreach($pointcuts as $pointcut) {
            $aspects = self::$aspects->offsetExists($pointcut) ? self::$aspects[$pointcut] : array();
            $aspects[] = $this;
            self::$aspects[$pointcut] = $aspects;
        }
    }

    /**
     * Returns joinpoints list for requested aspect object
     *
     * This function try to found joinpoints in static cache. If there is not definition of joinpoints for class, then
     * AspectObject->getClosures() returns list of all start points for class, joinpoints will be created for that
     * points and all aspects will be applied before returning result.
     *
     * @param \go\core\AspectObject $aspectObject
     * @return \org\aopalliance\intercept\Joinpoint[]
     */
    final public static function getJoinPoints(\go\core\AspectObject $aspectObject)
    {
        $className = get_class($aspectObject);
        if (empty(self::$joinPoints[$className])) {
            $joinPoints = array();
            $aspectObjectClosures = $aspectObject->getClosures();
            foreach($aspectObjectClosures as $name => $closure) {
                $joinPoints[$name] = new JoinpointObject($closure);
            }
            foreach(self::$aspects as $pointcut) {
                foreach(self::$aspects[$pointcut] as $aspect) {
                    $pointcut($className, $joinPoints, $aspect);
                }
            }
            self::$joinPoints[$className] = $joinPoints;
        }
        return self::$joinPoints[$className];
    }
}

/** Wildcards mark for selection */
const WILDCARD = '*';
const WILDCARD_POINTS = '..';

/** Children cross-cut modifier */
const CHILDREN = '+';

/**
 * @param string $specification call([access modifier] ClassName[+]->method(*));
 * @return Pointcut
 */
function call($specification)
{
    $matches   = array();
    $wildCards = array(
        '\\' . WILDCARD        => '.*',
        '\\' . WILDCARD_POINTS => '.*'
    );
    if (!preg_match('/^(\w*?)?\s*([^\s-+]*)(\\' . CHILDREN . ')?->(.*)/', $specification, $matches)){
        throw new \InvalidArgumentException('Specification of pointcut is not correct');
    }
    list(, $accessModifier, $classNameMask, $withChildren, $joinPointMask) = $matches;
    $classNameMask = '/^' . strtr(preg_quote($classNameMask, '/'), $wildCards) . '$/';
    $joinPointMask = '/^' . strtr(preg_quote($joinPointMask, '/'), $wildCards) . '$/';

    return new Pointcut(
        function ($className, $joinPoints, Aspect $aspect) use($accessModifier, $classNameMask, $withChildren, $joinPointMask) {
            if (preg_match($classNameMask, $className) || ($withChildren && is_subclass_of($className, $classNameMask))) {
                // todo: do not copy advices to joinpoint, as they stored in Aspect
                foreach ($joinPoints as $joinPointName => $joinPoint) {
                    if (preg_match($joinPointMask, $joinPointName)) {
                        // take all public properties from aspect and put them to joinpoint
                        foreach (array_filter(get_object_vars($aspect)) as $adviceName => $advices) {
                            $joinPoint->$adviceName = $advices;
                        }
                    }
                }
            }
    });
}