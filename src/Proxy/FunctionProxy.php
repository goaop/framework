<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Go\Aop\Advice;
use Go\Aop\Framework\ReflectionFunctionInvocation;
use Go\Aop\Intercept\FunctionInvocation;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use ReflectionFunction;
use ReflectionParameter as Parameter;

use TokenReflection\ReflectionFileNamespace;
use TokenReflection\ReflectionParameter as ParsedParameter;
use TokenReflection\ReflectionFunction as ParsedFunction;

/**
 * Function proxy builder that is used to generate a proxy-function from the list of joinpoints
 */
class FunctionProxy extends AbstractProxy
{

    /**
     * List of advices for functions
     *
     * @var array
     */
    protected static $functionAdvices = array();

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
     * Constructs functions stub class from namespace Reflection
     *
     * @param ReflectionFileNamespace $namespace Reflection of namespace
     * @param array $advices List of function advices
     *
     * @throws \InvalidArgumentException for invalid classes
     */
    public function __construct($namespace, array $advices = array())
    {
        if (!$namespace instanceof ReflectionFileNamespace) {
            throw new \InvalidArgumentException("Invalid argument for namespace");
        }

        parent::__construct($advices);
        $this->namespace = $namespace;

        if (empty($advices[AspectContainer::FUNCTION_PREFIX])) {
            return;
        }

        foreach ($advices[AspectContainer::FUNCTION_PREFIX] as $pointName => $value) {
            $function = new ReflectionFunction($pointName);
            $this->override($function, $this->getJoinpointInvocationBody($function));
        }
    }

    /**
     * Returns a joinpoint for specific function in the namespace
     *
     * @param string $joinPointName Special joinpoint name
     * @param string $namespace Name of the namespace
     *
     * @return FunctionInvocation
     */
    public static function getJoinPoint($joinPointName, $namespace)
    {
        /** @var LazyAdvisorAccessor $accessor */
        static $accessor = null;

        if (!$accessor) {
            $accessor  = AspectKernel::getInstance()->getContainer()->get('aspect.advisor.accessor');
        }

        $advices = self::$functionAdvices[$namespace][AspectContainer::FUNCTION_PREFIX][$joinPointName];

        $filledAdvices = array();
        foreach ($advices as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
        }

        return new ReflectionFunctionInvocation($joinPointName, $filledAdvices);
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
        self::$functionAdvices[$namespace] = $advices;
    }

    /**
     * Override function with new body
     *
     * @param ReflectionFunction|ParsedFunction $function Function reflection
     * @param string $body New body for function
     *
     * @return $this
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
        $functionsCode = (
            "<?php\n" . // Start of file header
            $this->namespace->getDocComment() . "\n" . // Doc-comment for file
            'namespace ' . // 'namespace' keyword
            $this->namespace->getName() . // Name
            ";\n" . // End of namespace name
            join("\n", $this->functionsCode) // Function definitions
        );

        return $functionsCode
            // Inject advices on call
            . PHP_EOL
            . '\\' . __CLASS__ . "::injectJoinPoints('"
                . $this->namespace->getName() . "',"
                . var_export($this->advices, true) . ");";
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
        static $inMemoryCache = array();

        $functionName = $function->getName();
        if (isset($inMemoryCache[$functionName])) {
            return $inMemoryCache[$functionName];
        }

        $code = (
            preg_replace('/ {4}|\t/', '', $function->getDocComment()) ."\n" . // Original doc-comment
            'function ' . // 'function' keyword
            ($function->returnsReference() ? '&' : '') . // By reference symbol
            $functionName . // Function name
            '(' . // Start of parameters
            join(', ', $this->getParameters($function->getParameters())) . // List of parameters
            ")\n" . // End of parameters
            "{\n" . // Start of function body
            $this->indent($body) . "\n" . // Body of function
            "}\n" // End of function body
        );

        $inMemoryCache[$functionName] = $code;

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
        $class   = '\\' . __CLASS__;

        $dynamicArgs   = false;
        $hasOptionals  = false;
        $hasReferences = false;

        $argValues = array_map(function ($param) use (&$dynamicArgs, &$hasOptionals, &$hasReferences) {
            /** @var $param Parameter|ParsedParameter */
            $byReference   = $param->isPassedByReference();
            $dynamicArg    = $param->name == '...';
            $dynamicArgs   = $dynamicArgs || $dynamicArg;
            $hasOptionals  = $hasOptionals || ($param->isOptional() && !$param->isDefaultValueAvailable());
            $hasReferences = $hasReferences || $byReference;

            return ($byReference ? '&' : '') . '$' . $param->name;
        }, $function->getParameters());

        if ($dynamicArgs) {
            // Remove last '...' argument
            array_pop($argValues);
        }

        $args = join(', ', $argValues);

        if ($dynamicArgs) {
            $args = $hasReferences ? "array($args) + \\func_get_args()" : '\func_get_args()';
        } elseif ($hasOptionals) {
            $args = "\\array_slice(array($args), 0, \\func_num_args())";
        } else {
            $args = "array($args)";
        }

        return <<<BODY
static \$__joinPoint = null;
if (!\$__joinPoint) {
    \$__joinPoint = {$class}::getJoinPoint('{$function->name}', __NAMESPACE__);
}
return \$__joinPoint->__invoke($args);
BODY;
    }

}
