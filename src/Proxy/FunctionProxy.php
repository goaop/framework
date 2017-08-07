<?php
declare(strict_types = 1);
/*
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
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionFunction;

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
    protected static $functionAdvices = [];

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
    protected $functionsCode = [];

    /**
     * Constructs functions stub class from namespace Reflection
     *
     * @param ReflectionFileNamespace $namespace Reflection of namespace
     * @param array $advices List of function advices
     *
     * @throws \InvalidArgumentException for invalid classes
     */
    public function __construct(ReflectionFileNamespace $namespace, array $advices = [])
    {
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
            $accessor = AspectKernel::getInstance()->getContainer()->get('aspect.advisor.accessor');
        }

        $advices = self::$functionAdvices[$namespace][AspectContainer::FUNCTION_PREFIX][$joinPointName];

        $filledAdvices = [];
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
    public static function injectJoinPoints($namespace, array $advices = [])
    {
        self::$functionAdvices[$namespace] = $advices;
    }

    /**
     * Override function with new body
     *
     * @param ReflectionFunction $function Function reflection
     * @param string $body New body for function
     *
     * @return $this
     */
    public function override(ReflectionFunction $function, $body)
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
     * Creates definition for trait method body
     *
     * @param ReflectionFunction $function Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody(ReflectionFunction $function)
    {
        $class = '\\' . __CLASS__;

        $args = $this->prepareArgsLine($function);

        $return = 'return ';
        if (PHP_VERSION_ID >= 70100 && $function->hasReturnType()) {
            $returnType = (string) $function->getReturnType();
            if ($returnType === 'void') {
                // void return types should not return anything
                $return = '';
            }
        }

        return <<<BODY
static \$__joinPoint = null;
if (!\$__joinPoint) {
    \$__joinPoint = {$class}::getJoinPoint('{$function->name}', __NAMESPACE__);
}
{$return}\$__joinPoint->__invoke($args);
BODY;
    }

}
