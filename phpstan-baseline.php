<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Property Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadataInfo\\<object\\>\\:\\:\\$table \\(array\\{name\\: string, schema\\?\\: string, indexes\\?\\: array, uniqueConstraints\\?\\: array, options\\?\\: array\\<string, mixed\\>, quoted\\?\\: bool\\}\\) does not accept array\\{\\}\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Bridge/Doctrine/MetadataLoadInterceptor.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function file_get_contents\\(\\) on a separate line has no effect\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CacheWarmer.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
