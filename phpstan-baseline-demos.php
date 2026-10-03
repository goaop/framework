<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset non\\-falsy\\-string on mixed\\.$#',
	'identifier' => 'offsetAccess.nonOffsetAccessible',
	'count' => 2,
	'path' => __DIR__ . '/demos/Demo/Aspect/CachingAspect.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$haystack of function str_starts_with expects string, mixed given\\.$#',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/demos/Demo/Aspect/DynamicMethodsAspect.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#4 \\(mixed\\) of echo cannot be converted to string\\.$#',
	'identifier' => 'echo.nonString',
	'count' => 2,
	'path' => __DIR__ . '/demos/Demo/Aspect/DynamicMethodsAspect.php',
];
$ignoreErrors[] = [
	'message' => '#^Trait Demo\\\\Aspect\\\\Introduce\\\\SerializableImpl is used zero times and is not analysed\\.$#',
	'identifier' => 'trait.unused',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Aspect/Introduce/SerializableImpl.php',
];
$ignoreErrors[] = [
	'message' => '#^Trait Demo\\\\Aspect\\\\Introduce\\\\StringableImpl is used zero times and is not analysed\\.$#',
	'identifier' => 'trait.unused',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Aspect/Introduce/StringableImpl.php',
];
$ignoreErrors[] = [
	'message' => '#^Class Demo\\\\Example\\\\DynamicMethodsDemo has PHPDoc tag @method for method find\\(\\) parameter \\#1 \\$args with no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/DynamicMethodsDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\DynamicMethodsDemo\\:\\:__call\\(\\) has parameter \\$args with no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/DynamicMethodsDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\DynamicMethodsDemo\\:\\:__callStatic\\(\\) has parameter \\$args with no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/DynamicMethodsDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\FunctionDemo\\:\\:testArrayFunctions\\(\\) has parameter \\$data with no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/FunctionDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\FunctionDemo\\:\\:testArrayFunctions\\(\\) return type has no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/FunctionDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array of function array_flip expects array\\<int\\|string\\>, array\\<int\\<0, max\\>, mixed\\> given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/FunctionDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$array of function array_unique expects an array of values castable to string, list given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/FunctionDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function htmlspecialchars expects string, string\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/FunctionDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Unsafe usage of new static\\(\\)\\.$#',
	'identifier' => 'new.static',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/LoggingDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Property Demo\\\\Example\\\\PropertyDemo\\:\\:\\$indirectModificationCheck type has no value type specified in iterable type array\\.$#',
	'identifier' => 'missingType.iterableValue',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/PropertyDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\UserFluentDemo\\:\\:setName\\(\\) has no return type specified\\.$#',
	'identifier' => 'missingType.return',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/UserFluentDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\UserFluentDemo\\:\\:setPassword\\(\\) has no return type specified\\.$#',
	'identifier' => 'missingType.return',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/UserFluentDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Demo\\\\Example\\\\UserFluentDemo\\:\\:setSurname\\(\\) has no return type specified\\.$#',
	'identifier' => 'missingType.return',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Example/UserFluentDemo.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$string of function htmlspecialchars expects string, string\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/Demo/Highlighter.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to method Demo\\\\Example\\\\FunctionDemo\\:\\:testArrayFunctions\\(\\) on a separate line has no effect\\.$#',
	'identifier' => 'method.resultUnused',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setPassword\\(\\) on mixed\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setSurname\\(\\) on mixed\\.$#',
	'identifier' => 'method.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Construct empty\\(\\) is not allowed\\. Use more strict comparison\\.$#',
	'identifier' => 'empty.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Instanceof between Demo\\\\Example\\\\UserFluentDemo and Go\\\\Aop\\\\Proxy will always evaluate to false\\.$#',
	'identifier' => 'instanceof.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Loose comparison via "\\=\\=" is not allowed\\.$#',
	'identifier' => 'equal.notAllowed',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in an if condition, Demo\\\\Example\\\\CacheableDemo\\|Demo\\\\Example\\\\DynamicMethodsDemo\\|Demo\\\\Example\\\\FunctionDemo\\|Demo\\\\Example\\\\HumanDemo\\|Demo\\\\Example\\\\IntroductionDemo\\|Demo\\\\Example\\\\LoggingDemo\\|Demo\\\\Example\\\\PropertyDemo\\|Demo\\\\Example\\\\UserFluentDemo\\|null given\\.$#',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$fileName of static method Go\\\\Instrument\\\\Transformer\\\\MagicConstantTransformer\\:\\:resolveFileName\\(\\) expects string, string\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$path of function basename expects string, string\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$separator of function explode expects non\\-empty\\-string, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$string of function explode expects string, string\\|false given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/demos/index.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
