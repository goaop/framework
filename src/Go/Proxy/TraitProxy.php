<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Proxy;

use ReflectionClass;
use ReflectionMethod as Method;
use ReflectionParameter as Parameter;

use Go\Aop\Advice;
use Go\Aop\IntroductionInfo;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

use TokenReflection\ReflectionClass as ParsedClass;
use TokenReflection\ReflectionMethod as ParsedMethod;
use TokenReflection\ReflectionParameter as ParsedParameter;

/**
 * AOP Factory that is used to generate trait proxy from joinpoints
 */
class TraitProxy extends ClassProxy
{

    /**
     * List of advices for traits
     *
     * @var array
     */
    protected static $traitAdvices = array();

    /**
     * Generates an child code by parent class reflection and joinpoints for it
     *
     * @param ReflectionClass|ParsedClass $parent Parent class reflection
     * @param array|Advice[] $advices List of advices for
     *
     * @throws \InvalidArgumentException for unsupported advice type
     * @return ClassProxy
     */
    public static function generate($parent, array $advices)
    {
        $traitChild = new self($parent, $parent->getShortName(), $advices);
        if (!empty($advices)) {
            foreach ($advices as $name => $value) {

                list ($type, $pointName) = explode(':', $name, 2);
                switch ($type) {
                    case AspectContainer::METHOD_PREFIX:
                    case AspectContainer::STATIC_METHOD_PREFIX:
                        $traitChild->overrideMethod($parent->getMethod($pointName));
                        break;

                    case AspectContainer::PROPERTY_PREFIX:
                    case AspectContainer::INTRODUCTION_TRAIT_PREFIX:
                        continue;

                    default:
                        throw new \InvalidArgumentException("Unsupported point `$type`");
                }
            }
        }
        return $traitChild;
    }

    /**
     * Inject advices for given trait
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     *
     * @param string $className Aop child proxy class
     * @param array|Advice[] $advices List of advices to inject into class
     *
     * @return void
     */
    public static function injectJoinPoints($className, array $advices = array())
    {
        if (!$advices) {
            $container = AspectKernel::getInstance()->getContainer();
            $advices   = $container->getAdvicesForClass($className);
        }
        self::$traitAdvices[$className] = $advices;
    }


    public static function getJoinPoint($traitName, $className, $pointName)
    {
        $advices = self::$traitAdvices[$traitName][$pointName];
        return self::wrapSingleJoinPoint($className, $pointName . '➩', $advices);
    }


    /**
     * Creates definition for trait method body
     *
     * @param Method|ParsedMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody($method)
    {
        $isStatic = $method->isStatic();
        $class    = '\\' . __CLASS__;
        $scope    = $isStatic ? 'get_called_class()' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $args = join(', ', array_map(function ($param) {
            /** @var $param Parameter|ParsedParameter */
            $byReference = $param->isPassedByReference() ? '&' : '';
            return $byReference . '$' . $param->name;
        }, $method->getParameters()));

        $args = $scope . ($args ? ", array($args)" : '');
        return <<<BODY
static \$__joinPoint = null;
if (!\$__joinPoint) {
    \$__joinPoint = {$class}::getJoinPoint(__TRAIT__, __CLASS__, '{$prefix}:{$method->name}');
}
return \$__joinPoint->__invoke($args);
BODY;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        $serialized = serialize($this->advices);
        ksort($this->methodsCode);
        $classCode = sprintf("%s\ntrait %s\n{\n%s\n\n%s\n}",
            $this->class->getDocComment(),
            $this->name,
            $this->indent(
                'use ' . join(', ', array(-1 => $this->parentClassName) + $this->traits) .
                $this->getMethodAliasesCode()
            ),
            $this->indent(join("\n", $this->methodsCode))
        );

        return $classCode
            // Inject advices on call
            . PHP_EOL
            . '\\' . __CLASS__ . "::injectJoinPoints('" . $this->class->name . "', unserialize('{$serialized}'));";
    }

    private function getMethodAliasesCode()
    {
        $aliasesLines = array();
        foreach (array_keys($this->methodsCode) as $methodName) {
            $aliasesLines[] = "{$this->parentClassName}::{$methodName} as protected {$methodName}➩;";
        }
        return "{\n " . $this->indent(join("\n", $aliasesLines)) . "\n}";
    }
}
