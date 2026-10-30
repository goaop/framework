<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Exception;

use Closure;
use Dissect\Lexer\CommonToken;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Core\AspectContainer;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Throwable;

/**
 * Catalogue of the messages built by the named constructors: most of them guard paths that are hard to reach
 */
final class ExceptionMessagesTest extends TestCase
{
    public int $fixtureProperty = 0;

    /**
     * @return array<string, array{Closure(): Throwable, class-string<Throwable>, string}>
     */
    public static function namedConstructors(): array
    {
        $method   = new ReflectionMethod(self::class, 'fixtureMethod');
        $property = new ReflectionProperty(self::class, 'fixtureProperty');
        $location = sprintf('%s:%d', $method->getFileName(), $method->getStartLine());

        return [
            // Configuration
            'kernel not created'          => [
                static fn() => InvalidConfigurationException::kernelNotCreated(),
                InvalidConfigurationException::class,
                'Aspect kernel is not initialized yet, call init() on the kernel of the application first',
            ],
            'kernel not initialized'      => [
                static fn() => InvalidConfigurationException::kernelNotInitialized('App\Kernel'),
                InvalidConfigurationException::class,
                'App\Kernel is not initialized yet, call init() first',
            ],
            'kernel not in loader'        => [
                static fn() => InvalidConfigurationException::kernelNotConfiguredInLoader('/app/aop.php'),
                InvalidConfigurationException::class,
                'Aspect kernel was not initialized by the loader /app/aop.php: call init() on your kernel there',
            ],
            'unknown feature'             => [
                static fn() => InvalidConfigurationException::unknownFeature(64),
                InvalidConfigurationException::class,
                'Unknown feature 64, use Go\Aop\Features constants',
            ],
            'unknown option'              => [
                static fn() => InvalidConfigurationException::unknownOption('cachDir', 'cacheDir', ['cacheDir', 'debug']),
                InvalidConfigurationException::class,
                'Unknown kernel option "cachDir", did you mean "cacheDir"?. Known options are: cacheDir, debug.',
            ],
            'unknown option, no guess'    => [
                static fn() => InvalidConfigurationException::unknownOption('foo', null, ['debug']),
                InvalidConfigurationException::class,
                'Unknown kernel option "foo". Known options are: debug.',
            ],
            'missing cache dir'           => [
                static fn() => InvalidConfigurationException::missingCacheDir(),
                InvalidConfigurationException::class,
                'Option "cacheDir" is required: provide the path to the cache directory.',
            ],
            'cache file mode, int'        => [
                static fn() => InvalidConfigurationException::invalidCacheFileMode(0444),
                InvalidConfigurationException::class,
                'Option "cacheFileMode" must be an integer permission mask between 0600 and 0777 '
                . 'that grants the owner read and write access, got 0444.',
            ],
            'cache file mode, type'       => [
                static fn() => InvalidConfigurationException::invalidCacheFileMode('0644'),
                InvalidConfigurationException::class,
                'Option "cacheFileMode" must be an integer permission mask between 0600 and 0777 '
                . 'that grants the owner read and write access, got string.',
            ],
            'features, int'               => [
                static fn() => InvalidConfigurationException::invalidFeatures(1024),
                InvalidConfigurationException::class,
                'Option "features" must be a combination of Go\Aop\Features constants, got 1024.',
            ],
            'features, type'              => [
                static fn() => InvalidConfigurationException::invalidFeatures([]),
                InvalidConfigurationException::class,
                'Option "features" must be a combination of Go\Aop\Features constants, got array.',
            ],
            'container class, name'       => [
                static fn() => InvalidConfigurationException::containerClassNotFound('App\Missing'),
                InvalidConfigurationException::class,
                'Container class "App\Missing" does not exist.',
            ],
            'container class, type'       => [
                static fn() => InvalidConfigurationException::containerClassNotFound(42),
                InvalidConfigurationException::class,
                'Container class int does not exist.',
            ],
            'container class not aspect'  => [
                static fn() => InvalidConfigurationException::containerClassNotAspectContainer(stdClass::class),
                InvalidConfigurationException::class,
                'Container class "stdClass" must extend ' . AspectContainer::class . '.',
            ],
            'lazy service id'             => [
                static fn() => InvalidConfigurationException::invalidLazyServiceId('not a class'),
                InvalidConfigurationException::class,
                'Lazy service id must be a valid class name, "not a class" given',
            ],
            'own delegate'                => [
                static fn() => InvalidConfigurationException::containerIsOwnDelegate(),
                InvalidConfigurationException::class,
                'Container can not be its own delegate',
            ],
            'cache dir not creatable'     => [
                static fn() => InvalidConfigurationException::cacheDirectoryNotCreatable('/cache/aop', '/cache'),
                InvalidConfigurationException::class,
                'Can not create the cache directory /cache/aop: its parent directory /cache does not exist or is not writable',
            ],
            'cache dir not writable'      => [
                static fn() => InvalidConfigurationException::cacheDirectoryNotWritable('/cache/aop'),
                InvalidConfigurationException::class,
                'Cache directory /cache/aop is not writable',
            ],
            'warmer without cache dir'    => [
                static fn() => InvalidConfigurationException::cacheWarmerWithoutCacheDir(),
                InvalidConfigurationException::class,
                'Cache warmer requires the `cacheDir` kernel option to be configured',
            ],
            'path outside root'           => [
                static fn() => InvalidConfigurationException::pathOutsideRoot('/other', '/app'),
                InvalidConfigurationException::class,
                'Include path /other is not below the application directory /app',
            ],
            'loader argument'             => [
                static fn() => InvalidConfigurationException::loaderArgumentNotString(),
                InvalidConfigurationException::class,
                'Loader argument must be a string',
            ],
            'loader path'                 => [
                static fn() => InvalidConfigurationException::invalidLoaderPath('aop.php'),
                InvalidConfigurationException::class,
                'Loader file aop.php does not exist or is not readable',
            ],
            'invalid worker count'        => [
                static fn() => InvalidConfigurationException::invalidWorkerCount('many'),
                InvalidConfigurationException::class,
                'Number of workers must be a positive integer or "auto", got "many"',
            ],
            'native lazy objects'         => [
                static fn() => InvalidConfigurationException::nativeLazyObjectsRequired('App\User', 'name'),
                InvalidConfigurationException::class,
                'Mapped property App\User::$name of a woven entity is intercepted by an aspect, which requires '
                . 'Doctrine native lazy objects: call $configuration->enableNativeLazyObjects(true).',
            ],
            'entity directory'            => [
                static fn() => InvalidConfigurationException::entityDirectoryNotFound('/app/Entity'),
                InvalidConfigurationException::class,
                'Entity directory "/app/Entity" does not exist.',
            ],
            'service not registered'      => [
                static fn() => ContainerException::serviceNotProperlyRegistered('App\Service'),
                ContainerException::class,
                'Service App\Service is not properly registered',
            ],
            'value not defined'           => [
                static fn() => ServiceNotFoundException::valueNotDefined('app.value'),
                ServiceNotFoundException::class,
                'Value app.value is not defined in the container',
            ],

            // Aspect definition and parse time
            'multiple advice attributes'  => [
                static fn() => AspectException::multipleAdviceAttributes($method, 2),
                AspectException::class,
                'Advice method ' . self::class . '::fixtureMethod() declares 2 advice attributes, '
                . "only one is supported per method ({$location})",
            ],
            'non-public advice'           => [
                static fn() => AspectException::nonPublicAdviceMethod($method),
                AspectException::class,
                'Advice method ' . self::class . '::fixtureMethod() must be public; '
                . 'first-class advice callables require all advice methods to be public',
            ],
            'advice attribute'            => [
                static fn() => AspectException::unsupportedAdviceAttribute(new stdClass()),
                AspectException::class,
                'Unsupported method meta class: stdClass',
            ],
            'property attribute'          => [
                static fn() => AspectException::unsupportedPropertyAttribute('App\Inject', $property),
                AspectException::class,
                'Attribute App\Inject is not supported on aspect property ' . self::class
                . '::$fixtureProperty, only #[DeclareParents] is',
            ],
            'introduction attribute'      => [
                static fn() => AspectException::unsupportedIntroductionAttribute(new stdClass()),
                AspectException::class,
                'Unsupported attribute class: stdClass',
            ],
            'pointcut reference'          => [
                static fn() => AspectException::pointcutReferenceNotPointcut('App->pc', 42),
                AspectException::class,
                'Pointcut reference App->pc resolves to int, not to a pointcut',
            ],
            'advice method name'          => [
                static fn() => AspectException::adviceMethodNameRequired(),
                AspectException::class,
                'Advice method name is required when an aspect class name is given',
            ],
            'advisor without advice'      => [
                static fn() => AspectException::advisorWithoutAdvice('App->advice', null),
                AspectException::class,
                'Advisor App->advice provides null instead of advice instance',
            ],
            'unsupported advice'          => [
                static fn() => AspectException::unsupportedAdvice('App->advice', new TraitIntroductionInfo(self::class, Throwable::class)),
                AspectException::class,
                'Advisor App->advice uses unsupported advice ' . TraitIntroductionInfo::class
                . '; only framework aspect-method interceptors can be generated',
            ],
            'invalid expression'          => [
                static fn() => PointcutSyntaxException::invalidExpression('execution(', new LogicException('eof')),
                PointcutSyntaxException::class,
                'Invalid pointcut expression `execution(`: eof',
            ],
            'unrecognized, method'        => [
                static fn() => PointcutSyntaxException::unrecognizedLexicalStructure('execution(', $method, new LogicException()),
                PointcutSyntaxException::class,
                'Can not recognize the lexical structure `execution(` before ' . self::class . "->fixtureMethod, defined in {$location}",
            ],
            'unrecognized, property'      => [
                static fn() => PointcutSyntaxException::unrecognizedLexicalStructure('within(', $property, new LogicException()),
                PointcutSyntaxException::class,
                'Can not recognize the lexical structure `within(` before ' . self::class . '->fixtureProperty, defined in '
                . $property->getDeclaringClass()->getFileName() . ':0',
            ],
            'unexpected token, class'     => [
                static fn() => PointcutSyntaxException::unexpectedToken(
                    'within(->)',
                    new ReflectionClass(self::class),
                    new UnexpectedTokenException(new CommonToken('->', '->', 1), ['namePart']),
                ),
                PointcutSyntaxException::class,
                'Unexpected token -> in the `within(->)` before ' . self::class . ', defined in '
                . new ReflectionClass(self::class)->getFileName() . ':' . new ReflectionClass(self::class)->getStartLine()
                . '.' . PHP_EOL . 'Expected one of: namePart',
            ],
            'parse result'                => [
                static fn() => PointcutSyntaxException::unexpectedParseResult(),
                PointcutSyntaxException::class,
                'Expected instance of Pointcut to be received during parsing',
            ],
            'token value'                 => [
                static fn() => PointcutSyntaxException::tokenValueNotString(),
                PointcutSyntaxException::class,
                'Token value must be a string',
            ],
            'modifier alternatives'       => [
                static fn() => PointcutSyntaxException::multipleModifierAlternatives(),
                PointcutSyntaxException::class,
                'Only one group of modifier alternatives (a|b) is supported per member pattern',
            ],
            'empty return type'           => [
                static fn() => PointcutSyntaxException::emptyReturnType(),
                PointcutSyntaxException::class,
                'Return type name must not be empty',
            ],
            'name pattern'                => [
                static fn() => PointcutSyntaxException::namePatternNotMatchable('foo*', 'Backtrack limit exhausted'),
                PointcutSyntaxException::class,
                'Name pattern `foo*` can not be matched: Backtrack limit exhausted',
            ],
            'return type pattern'         => [
                static fn() => PointcutSyntaxException::returnTypePatternNotMatchable('Foo*', 'Backtrack limit exhausted'),
                PointcutSyntaxException::class,
                'Return type pattern `Foo*` can not be matched: Backtrack limit exhausted',
            ],

            // Weave time
            'filter already registered'   => [
                static fn() => WeavingException::streamFilterAlreadyRegistered('go.aop'),
                WeavingException::class,
                'Stream filter go.aop is already registered',
            ],
            'filter registration failed'  => [
                static fn() => WeavingException::streamFilterRegistrationFailed('go.aop'),
                WeavingException::class,
                'Stream filter go.aop could not be registered, the name is taken by another filter',
            ],
            'filter not registered'       => [
                static fn() => WeavingException::streamFilterNotRegistered(),
                WeavingException::class,
                'Stream filter is not registered yet, call SourceTransformingLoader::register() first',
            ],
            'invalid stream'              => [
                static fn() => WeavingException::invalidStream(null),
                WeavingException::class,
                'Stream must be a valid resource, got null',
            ],
            'stream without uri'          => [
                static fn() => WeavingException::streamWithoutUri(),
                WeavingException::class,
                'Stream has no uri, only file streams can be transformed',
            ],
            'transformer failed'          => [
                static fn() => WeavingException::transformerFailed('App\Transformer', '/app/Foo.php', new LogicException('boom')),
                WeavingException::class,
                'App\Transformer failed to transform /app/Foo.php: boom',
            ],
            'warmup worker not started'   => [
                static fn() => WeavingException::warmupWorkerNotStarted('php -r ...'),
                WeavingException::class,
                'Cache warmup worker process could not be started: php -r ...',
            ],
            'warmup worker failed'        => [
                static fn() => WeavingException::warmupWorkerFailedToStart(255, "PHP Fatal error: boom\n"),
                WeavingException::class,
                "Cache warmup worker process exited with code 255 before it was ready to weave:\nPHP Fatal error: boom",
            ],
            'prebuilt cache version'      => [
                static fn() => WeavingException::prebuiltCacheVersionMismatch('/cache'),
                WeavingException::class,
                'The AOP cache in /cache was built by another version of the framework, '
                . 'rebuild it with `bin/aspect cache:warmup:aop` before using Features::PREBUILT_CACHE',
            ],
            'cache dir not created'       => [
                static fn() => WeavingException::cacheDirectoryNotCreated('/cache/aop'),
                WeavingException::class,
                'Unable to create cache directory "/cache/aop".',
            ],
            'cache file not written'      => [
                static fn() => WeavingException::cacheFileNotWritten('/cache/aop/Foo.php'),
                WeavingException::class,
                'Unable to write cache file "/cache/aop/Foo.php".',
            ],
            'token positions'             => [
                static fn() => WeavingException::declarationWithoutTokenPositions('Foo'),
                WeavingException::class,
                'Declaration of Foo has no token positions to weave',
            ],
            'missing class'               => [
                static fn() => WeavingException::constructorOfMissingClass('App\Missing'),
                WeavingException::class,
                'Cannot instantiate non-existent class: App\Missing',
            ],
            'parse table'                 => [
                static fn() => WeavingException::invalidParseTable(),
                WeavingException::class,
                'Pointcut parse table PointcutParseTable.php must return an array',
            ],
            'interceptor descriptor'      => [
                static fn() => WeavingException::invalidInterceptorDescriptor('advisor.id'),
                WeavingException::class,
                'Interceptor list expects generated interceptor descriptors, got string',
            ],
            'incomplete descriptor'       => [
                static fn() => WeavingException::incompleteInterceptorDescriptor(),
                WeavingException::class,
                'Aspect-backed interceptor descriptor is incomplete',
            ],
            'value type'                  => [
                static fn() => WeavingException::unsupportedValueType(new stdClass()),
                WeavingException::class,
                'Cannot generate AST node for value of type: stdClass',
            ],
            'type node'                   => [
                static fn() => WeavingException::unsupportedTypeNode('Expr_Variable'),
                WeavingException::class,
                'Unsupported type node Expr_Variable',
            ],
            'dnf type'                    => [
                static fn() => WeavingException::malformedDnfType('(A&B|C'),
                WeavingException::class,
                'Malformed DNF type: (A&B|C',
            ],
            'parameter closure default'   => [
                static fn() => WeavingException::parameterClosureDefault('callback'),
                WeavingException::class,
                'Cannot generate proxy for parameter $callback: PHP 8.5 Closure default values '
                . 'require goaop/parser-reflection for AST access.',
            ],
            'property closure default'    => [
                static fn() => WeavingException::propertyClosureDefault('App\Foo', 'callback'),
                WeavingException::class,
                'Cannot generate proxy for property App\Foo::$callback: PHP 8.5 Closure default values '
                . 'require goaop/parser-reflection for AST access.',
            ],
            'property not hookable'       => [
                static fn() => WeavingException::propertyNotHookable('App\Foo', 'bar'),
                WeavingException::class,
                'Property App\Foo::$bar cannot be intercepted with native hooks',
            ],
            'method by reference'         => [
                static fn() => WeavingException::methodReturnsByReference('App\Foo', 'bar'),
                WeavingException::class,
                'Method App\Foo::bar() returns by reference and can not be intercepted: the joinpoint returns '
                . 'values, so the reference would be lost. Exclude it from the pointcut with '
                . '"&& !matchReturningByReference()".',
            ],
            'trait override'              => [
                static fn() => WeavingException::traitImportedOverrideNotInterceptable('App\\Foo', 'bar', 'App\\BarTrait'),
                WeavingException::class,
                'Method App\\Foo::bar() is imported from trait App\\BarTrait and has #[\\Override], so it can not be '
                . 'intercepted: PHP copies the attribute to the private "barOriginalAlias" alias of the proxy, which '
                . 'overrides nothing. Exclude it from the pointcut with "&& !matchInherited()".',
            ],
            'function by reference'       => [
                static fn() => WeavingException::functionReturnsByReference('App\foo'),
                WeavingException::class,
                'Function App\foo() returns by reference and can not be intercepted: the joinpoint returns values, '
                . 'so the reference would be lost. Exclude it from the pointcut with "&& !matchReturningByReference()".',
            ],
            'interceptor without aspect'  => [
                static fn() => NotCompilableException::interceptorWithoutAspect(),
                NotCompilableException::class,
                'Could not compile an interceptor without valid aspect',
            ],
            'unsupported interceptor'     => [
                static fn() => NotCompilableException::unsupportedInterceptor('App\Interceptor'),
                NotCompilableException::class,
                'Cannot compile an instance of App\Interceptor into plain PHP',
            ],

            // Runtime
            'property not initialized'    => [
                static fn() => InvocationException::propertyNotInitialized('App\Foo', 'bar'),
                InvocationException::class,
                'Property App\Foo::$bar is not initialized yet',
            ],
            'typed property'              => [
                static fn() => InvocationException::typedPropertyNotInitialized('App\Foo', 'bar'),
                InvocationException::class,
                'Typed property App\Foo::$bar must not be accessed before initialization',
            ],
            'value to set'                => [
                static fn() => InvocationException::valueToSetUnavailable(),
                InvocationException::class,
                'Value to set is not available for READ access type',
            ],
            'advisor without closure'     => [
                static fn() => InvocationException::advisorWithoutClosure('App->advice'),
                InvocationException::class,
                'Advisor App->advice does not expose a closure advice',
            ],
            'closure scope'               => [
                static fn() => InvocationException::closureScopeUnresolvable(),
                InvocationException::class,
                'Cannot determine the scope class of the closure',
            ],
        ];
    }

    /**
     * @param Closure(): Throwable $factory
     * @param class-string<Throwable> $exceptionClass
     */
    #[DataProvider('namedConstructors')]
    public function testNamedConstructorBuildsItsMessage(Closure $factory, string $exceptionClass, string $message): void
    {
        $exception = $factory();

        $this->assertInstanceOf($exceptionClass, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testWrappingConstructorsChainThePreviousException(): void
    {
        $previous = new LogicException('cause');

        $this->assertSame($previous, PointcutSyntaxException::invalidExpression('x', $previous)->getPrevious());
        $this->assertSame($previous, WeavingException::transformerFailed('T', 'uri', $previous)->getPrevious());
        $this->assertSame(
            $previous,
            PointcutSyntaxException::unrecognizedLexicalStructure('x', new ReflectionClass(self::class), $previous)->getPrevious(),
        );
    }

    /**
     * Location used by the reflection-based messages
     */
    public function fixtureMethod(): void {}
}
