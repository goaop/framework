<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, list\\<Go\\\\Aop\\\\Intercept\\\\FieldAccessType\\|int\\|\\(T of object \\= object\\)\\|V \\= mixed\\>\\|null given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/ClassFieldAccess.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable property access on \\$this\\(Go\\\\Aop\\\\Framework\\\\ClassFieldAccess\\<T of object \\= object, V \\= mixed\\>\\)\\.$#',
	'identifier' => 'property.dynamicName',
	'count' => 4,
	'path' => __DIR__ . '/src/Aop/Framework/ClassFieldAccess.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, list\\<int\\|list\\<mixed\\>\\|T of object \\= object\\>\\|null given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/DynamicTraitAliasMethodInvocation.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable method call on Go\\\\Aop\\\\Aspect\\.$#',
	'identifier' => 'method.dynamicName',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/Interceptor.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, list\\<int\\|list\\<mixed\\>\\|\\(T of object \\= object\\)\\|null\\>\\|null given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/ReflectionConstructorInvocation.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/ReflectionFunctionInvocation.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, list\\<int\\|list\\<mixed\\>\\>\\|null given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/ReflectionFunctionInvocation.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, list\\<class\\-string\\<T of object \\= object\\>\\|int\\|list\\<mixed\\>\\>\\|null given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Framework/StaticTraitAliasMethodInvocation.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function in_array\\(\\) requires parameter \\#3 to be set\\.$#',
	'identifier' => 'function.strict',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Pointcut/MatchInheritedPointcut.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a negated boolean, int given\\.$#',
	'identifier' => 'booleanNot.exprNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Aop/Pointcut/ModifierPointcut.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Console/Command/DebugAdvisorCommand.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, string\\|false given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Console/Command/DebugAspectCommand.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 2,
	'path' => __DIR__ . '/src/Core/AdviceMatcher.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, int\\<0, 16\\> given on the left side\\.$#',
	'identifier' => 'booleanAnd.leftNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Core/AdviceMatcher.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, int\\<0, 4\\> given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Core/AdviceMatcher.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Core/AspectKernel.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable property access on PhpParser\\\\Node\\.$#',
	'identifier' => 'property.dynamicName',
	'count' => 1,
	'path' => __DIR__ . '/src/Core/Cache/AdvisorCacheCompiler.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Core/Cache/AdvisorCachePrinter.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function array_filter\\(\\) requires parameter \\#2 to be passed to avoid loose comparison semantics\\.$#',
	'identifier' => 'arrayFilter.strict',
	'count' => 1,
	'path' => __DIR__ . '/src/Core/Container.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CachePathManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a negated boolean, string\\|null given\\.$#',
	'identifier' => 'booleanNot.exprNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CachePathManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, string\\|null given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CachePathManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CacheWarmer.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 3,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/SourceTransformingLoader.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, int\\|false given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/SourceTransformingLoader.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 2,
	'path' => __DIR__ . '/src/Instrument/FileSystem/Enumerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, string\\|false given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/PathResolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a negated boolean, array\\<string\\>\\|string given\\.$#',
	'identifier' => 'booleanNot.exprNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/PathResolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a negated boolean, string\\|null given\\.$#',
	'identifier' => 'booleanNot.exprNotBoolean',
	'count' => 2,
	'path' => __DIR__ . '/src/Instrument/PathResolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, string\\|null given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/PathResolver.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/Transformer/ConstructorExecutionTransformer.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/Transformer/FilterInjectorTransformer.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a negated boolean, string\\|null given\\.$#',
	'identifier' => 'booleanNot.exprNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/Transformer/FilterInjectorTransformer.php',
];
$ignoreErrors[] = [
	'message' => '#^Short ternary operator is not allowed\\. Use null coalesce operator if applicable or consider using long ternary\\.$#',
	'identifier' => 'ternary.shortNotAllowed',
	'count' => 2,
	'path' => __DIR__ . '/src/Instrument/Transformer/FilterInjectorTransformer.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in &&, int\\|false given on the right side\\.$#',
	'identifier' => 'booleanAnd.rightNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/Transformer/StreamMetaData.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable property access on \\$this\\(Go\\\\Instrument\\\\Transformer\\\\StreamMetaData\\)\\.$#',
	'identifier' => 'property.dynamicName',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/Transformer/StreamMetaData.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 3,
	'path' => __DIR__ . '/src/Instrument/Transformer/WeavingTransformer.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 4,
	'path' => __DIR__ . '/src/Proxy/ClassProxyGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, string\\|false given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/ClassProxyGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/EnumProxyGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Go\\\\Proxy\\\\EnumProxyGenerator\\:\\:__construct\\(\\) does not call parent constructor from Go\\\\Proxy\\\\ClassProxyGenerator\\.$#',
	'identifier' => 'constructor.missingParentCall',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/EnumProxyGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/AttributeGroupsGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/ClassGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/EnumGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/FunctionGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/GeneratedCodePrinter.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/MethodGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/TraitGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Generator/ValueGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Part/FunctionCallArgumentListGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/TraitProxyGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Go\\\\Proxy\\\\TraitProxyGenerator\\:\\:__construct\\(\\) does not call parent constructor from Go\\\\Proxy\\\\ClassProxyGenerator\\.$#',
	'identifier' => 'constructor.missingParentCall',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/TraitProxyGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Framework/AbstractJoinpointTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Aop/Framework/ClassFieldAccessTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Framework/DynamicTraitAliasMethodInvocationTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Framework/GeneratedInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Aop/Framework/TheTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a ternary operator condition, int given\\.$#',
	'identifier' => 'ternary.condNotBoolean',
	'count' => 3,
	'path' => __DIR__ . '/tests/Aop/Pointcut/ModifierPointcutTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Pointcut/NamePointcutTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Pointcut/PointcutParserTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Pointcut/PointcutReferenceTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Pointcut/ReturnTypePointcutTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Support/LazyPointcutAdvisorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Bridge/Doctrine/WovenEntityClassLocatorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Short ternary operator is not allowed\\. Use null coalesce operator if applicable or consider using long ternary\\.$#',
	'identifier' => 'ternary.shortNotAllowed',
	'count' => 2,
	'path' => __DIR__ . '/tests/Console/ApplicationTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Console/Command/BaseAspectCommandTest.php',
];
$ignoreErrors[] = [
	'message' => '#^AnonymousClass[0-9a-f]+\\:\\:__construct\\(\\) does not call parent constructor from Go\\\\Instrument\\\\ClassLoading\\\\CacheWarmer\\.$#',
	'identifier' => 'constructor.missingParentCall',
	'count' => 3,
	'path' => __DIR__ . '/tests/Console/Command/CacheWarmupCommandInProcessTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Console/Command/CacheWarmupCommandInProcessTest.php',
];
$ignoreErrors[] = [
	'message' => '#^AnonymousClass[0-9a-f]+\\:\\:__construct\\(\\) does not call parent constructor from Go\\\\Instrument\\\\ClassLoading\\\\CacheWarmer\\.$#',
	'identifier' => 'constructor.missingParentCall',
	'count' => 1,
	'path' => __DIR__ . '/tests/Console/Command/DebugWeavingCommandInProcessTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Console/Command/DebugWeavingCommandInProcessTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 5,
	'path' => __DIR__ . '/tests/Core/AspectKernelTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 3,
	'path' => __DIR__ . '/tests/Core/AttributeAspectLoaderExtensionTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Core/Cache/AdvisorCacheCompilerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Core/Cache/CacheFileWriterTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Core/ContainerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Only numeric types are allowed in \\-, int\\<0, max\\>\\|false given on the left side\\.$#',
	'identifier' => 'minus.leftNonNumeric',
	'count' => 1,
	'path' => __DIR__ . '/tests/Core/ContainerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Core/IntroductionAspectExtensionTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 3,
	'path' => __DIR__ . '/tests/Instrument/ClassLoading/CachePathManagerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Instrument/ClassLoading/CacheWarmerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 3,
	'path' => __DIR__ . '/tests/Instrument/ClassLoading/SourceTransformingLoaderTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Instrument/FileSystem/EnumeratorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, string\\|null given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/tests/Instrument/PathResolverTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Instrument/Transformer/ConstructorExecutionTransformerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Variable method call on Go\\\\Instrument\\\\Transformer\\\\ConstructorExecutionTransformer\\.$#',
	'identifier' => 'method.dynamicName',
	'count' => 2,
	'path' => __DIR__ . '/tests/Instrument/Transformer/ConstructorExecutionTransformerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 2,
	'path' => __DIR__ . '/tests/Instrument/Transformer/StreamMetaDataTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Casting to string something that\'s already string\\.$#',
	'identifier' => 'cast.useless',
	'count' => 1,
	'path' => __DIR__ . '/tests/Instrument/Transformer/SyntaxFixturesWeavingTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Instrument/Transformer/WeavingTransformerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Casting to string something that\'s already string\\.$#',
	'identifier' => 'cast.useless',
	'count' => 4,
	'path' => __DIR__ . '/tests/Instrument/Transformer/WeavingTransformerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, int\\|false given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 8,
	'path' => __DIR__ . '/tests/Instrument/Transformer/WeavingTransformerTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Proxy/ClassProxyGeneratorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Proxy/FunctionProxyGeneratorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Proxy/Generator/InterceptorListGeneratorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Proxy/Generator/TypeGeneratorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to deprecated method expectExceptionMessage\\(\\) of class PHPUnit\\\\Framework\\\\TestCase\\:
https\\://github\\.com/sebastianbergmann/phpunit/issues/6560$#',
	'identifier' => 'method.deprecated',
	'count' => 1,
	'path' => __DIR__ . '/tests/Proxy/Generator/ValueGeneratorTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
