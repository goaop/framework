<?php declare(strict_types = 1);

// Baseline for tests/ only (see issue #633). Keep src/ suppressions in
// phpstan-baseline.php — this file must never contain src/ paths.

$ignoreErrors = [];

// MetadataLoadInterceptorTest deliberately builds Doctrine ClassMetadata for
// fake "Original" class names with intentionally malformed mapping data,
// and then asserts that MetadataLoadInterceptor::loadClassMetadata() reset it
// at runtime. Static analysis can neither accept the fake class-strings nor
// see the interceptor's mutations, so every finding below is a false positive
// rooted in that fixture setup.
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$name of class Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata constructor expects class\\-string\\<\\\\Some\\\\Class\\\\Name\\>, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$name of class Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata constructor expects class\\-string\\<Original\\\\Some\\\\Class\\\\Name\\>, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$name of class Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata constructor expects class\\-string\\<Original\\>, string given\\.$#',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Property Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata\\<Go\\\\Aop\\\\Bridge\\\\Doctrine\\\\EntityOriginal\\>\\:\\:\\$table \\(array\\{name\\: string, schema\\?\\: string, indexes\\?\\: array, uniqueConstraints\\?\\: array, options\\?\\: array\\<string, mixed\\>, quoted\\?\\: bool\\}\\) does not accept array\\{\'table_name\'\\}\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Property Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata\\<Go\\\\Aop\\\\Bridge\\\\Doctrine\\\\EntityOriginal\\>\\:\\:\\$customRepositoryClassName \\(class\\-string\\<Doctrine\\\\ORM\\\\EntityRepository\\>\\|null\\) does not accept string\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Property Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata\\<Go\\\\Aop\\\\Bridge\\\\Doctrine\\\\EntityOriginal\\>\\:\\:\\$fieldMappings \\(array\\<string, Doctrine\\\\ORM\\\\Mapping\\\\FieldMapping\\>\\) does not accept array\\<string, array\\<string, string\\>\\|Doctrine\\\\ORM\\\\Mapping\\\\FieldMapping\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to method PHPUnit\\\\Framework\\\\Assert\\:\\:assertFalse\\(\\) with true will always evaluate to false\\.$#',
	'identifier' => 'method.impossibleType',
	'count' => 3,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'mappedField\' on non\\-empty\\-array\\<string, array\\{columnName\\: \'mapped_field\', fieldName\\: \'mappedField\'\\}\\|Doctrine\\\\ORM\\\\Mapping\\\\FieldMapping\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'mapped_field\' on non\\-empty\\-array\\<string, string\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset \'mappedField\' on non\\-empty\\-array\\<mixed\\> in isset\\(\\) always exists and is not nullable\\.$#',
	'identifier' => 'isset.offset',
	'count' => 1,
	'path' => __DIR__ . '/tests/Aop/Bridge/Doctrine/MetadataLoadInterceptorTest.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
