<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Proxy;

use Go\Aop\Framework\ReflectionFunctionInvocation;
use Go\Core\AspectContainer;

use Go\Core\AspectKernel;
use ReflectionFunction;
use ReflectionParameter as Parameter;

use TokenReflection\ReflectionClass as ParsedClass;
use TokenReflection\ReflectionFileNamespace;
use TokenReflection\ReflectionParameter as ParsedParameter;
use TokenReflection\ReflectionMethod as ParsedMethod;
use TokenReflection\ReflectionFunction as ParsedFunction;

class FunctionProxy
{

    /**
     * List of advices for functions
     *
     * @var array
     */
    protected static $functionAdvices = array();

    /**
     * Indent for source code
     *
     * @var int
     */
    protected $indent = 4;

    /**
     * Name for the current namespace
     *
     * @var string
     */
    protected $namespace = '';

    /**
     * Source code for functions
     *
     * @var array Name of the function => source code for it
     */
    protected $functionsCode = array();

    /**
     * List of advices that are used for generation of stubs
     *
     * @var array
     */
    protected $advices = array();

    /**
     * Constructs functions stub class from namespace Reflection
     *
     * @param ReflectionFileNamespace $namespace Reflection of namespace
     * @param array $advices List of function advices
     *
     * @throws \InvalidArgumentException for invalid classes
     */
    protected function __construct($namespace, array $advices = array())
    {
        if (!$namespace instanceof ReflectionFileNamespace) {
            throw new \InvalidArgumentException("Invalid argument for namespace");
        }
        $this->advices   = $advices;
        $this->namespace = $namespace;
    }

    /**
     * Generates an child code by parent class reflection and joinpoints for it
     *
     * @param ReflectionFileNamespace $namespace Reflection of namespace
     * @param array|Advice[] $advices List of function advices
     *
     * @throws \InvalidArgumentException for unsupported advice type
     * @return ClassProxy
     */
    public static function generate($namespace, array $advices)
    {
        $functions = new self($namespace, $advices);
        if (!empty($advices)) {
            foreach ($advices as $name => $value) {

                list ($type, $pointName) = explode(':', $name, 2);
                switch ($type) {
                    case 'func':
                        $function = new ReflectionFunction($pointName);
                        $functions->override($function, $functions->getJoinpointInvocationBody($function));
                        break;

                    default:
                        throw new \InvalidArgumentException("Unsupported point `$type`");
                }
            }
        }
        return $functions;
    }

    public static function getJoinPoint($functionName, $namespace)
    {
        $advices = self::$functionAdvices[$namespace][$functionName];
        return new ReflectionFunctionInvocation($functionName, $advices);
    }


    /**
     * Inject advices for given trait
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     *
     * @param string $namespace Aop child proxy class
     * @param array|Advice[] $advices List of advices to inject into class
     *
     * @return void
     */
    public static function injectJoinPoints($namespace, array $advices = array())
    {
        if (!$advices) {
            $container = AspectKernel::getInstance()->getContainer();
            $advices   = $container->getAdvicesForFunctions($namespace);
        }
        self::$functionAdvices[$namespace] = $advices;
    }

    /**
     * Override function with new body
     *
     * @param ReflectionFunction|ParsedFunction $function Function reflection
     * @param string $body New body for function
     *
     * @return AbstractProxy
     */
    public function override($function, $body)
    {
        $this->functionsCode[$function->name] = $this->getOverriddenFunction($function, $body);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        $serialized = serialize($this->advices);
        ksort($this->functionsCode);

        $functionsCode = sprintf("<?php\n%s\nnamespace %s;\n%s",
            $this->namespace->getDocComment(),
            $this->namespace->getName(),
            join("\n", $this->functionsCode)
        );

        return $functionsCode
            // Inject advices on call
            . PHP_EOL
            . '\\' . __CLASS__ . "::injectJoinPoints('"
                . $this->namespace->getName() . "',"
                . " \unserialize(" . var_export($serialized, true) . "));";
    }

    /**
     * Creates a function code from Reflection
     *
     * @param ParsedFunction $function Reflection for function
     * @param string $body Body of function
     *
     * @return string
     */
    protected function getOverriddenFunction($function, $body)
    {
        $code = sprintf("%sfunction %s%s(%s)\n{\n%s\n}\n",
            preg_replace('/ {4}|\t/', '', $function->getDocComment()) ."\n",
            $function->returnsReference() ? '&' : '',
            $function->getName(),
            join(', ', $this->getParameters($function->getParameters())),
            $this->indent($body)
        );
        return $code;
    }

    /**
     * Creates definition for trait method body
     *
     * @param ReflectionFunction|ParsedFunction $function Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody($function)
    {
        $class  = '\\' . __CLASS__;
        $prefix = 'func';

        $args = join(', ', array_map(function ($param) {
            /** @var $param Parameter|ParsedParameter */
            $byReference = $param->isPassedByReference() ? '&' : '';
            return $byReference . '$' . $param->name;
        }, $function->getParameters()));

        $args = strpos($args, '...') === false ? "array($args)" : 'func_get_args()';
        return <<<BODY
static \$__joinPoint = null;
if (!\$__joinPoint) {
    \$__joinPoint = {$class}::getJoinPoint('{$prefix}:{$function->name}', __NAMESPACE__);
}
return \$__joinPoint->__invoke($args);
BODY;
    }


    /**
     * Indent block of code
     *
     * @param string $text Non-indented text
     *
     * @return string Indented text
     */
    protected function indent($text)
    {
        $pad   = str_pad('', $this->indent, ' ');
        $lines = array_map(function ($line) use ($pad) {
            return $pad . $line;
        }, explode("\n", $text));
        return join("\n", $lines);
    }

    /**
     * Returns list of string representation of parameters
     *
     * @param array|Parameter[]|ParsedParameter[] $parameters List of parameters
     *
     * @return array
     */
    protected function getParameters(array $parameters)
    {
        $parameterDefinitions = array();
        foreach ($parameters as $parameter) {
            if ($parameter->name == '...') {
                continue;
            }
            $parameterDefinitions[] = $this->getParameterCode($parameter);
        }
        return $parameterDefinitions;
    }

    /**
     * Return string representation of parameter
     *
     * @param Parameter|ParsedParameter $parameter Reflection parameter
     *
     * @return string
     */
    protected function getParameterCode($parameter)
    {
        $type = '';
        if ($parameter->isArray()) {
            $type = 'array';
        } elseif ($parameter->getClass()) {
            $type = '\\' . $parameter->getClass()->name;
        }
        $defaultValue = null;
        $isDefaultValueAvailable = $parameter->isDefaultValueAvailable();
        if ($isDefaultValueAvailable) {
            if ($parameter instanceof ParsedParameter) {
                $defaultValue = $parameter->getDefaultValueDefinition();
            } else {
                $defaultValue = var_export($parameter->getDefaultValue());
            }
        } elseif ($parameter->allowsNull()) {
            $defaultValue = 'null';
        }
        $code = sprintf('%s%s$%s%s',
            $type ? "$type " : '',
            $parameter->isPassedByReference() ? '&' : '',
            $parameter->name,
            $isDefaultValueAvailable ? (" = " . $defaultValue) : ''
        );
        return $code;
    }
}
