<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Property Doctrine\\\\ORM\\\\Mapping\\\\ClassMetadata\\<object\\>\\:\\:\\$table \\(array\\{name\\: string, schema\\?\\: string, indexes\\?\\: array, uniqueConstraints\\?\\: array, options\\?\\: array\\<string, mixed\\>, quoted\\?\\: bool\\}\\) does not accept array\\{\\}\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/src/Bridge/Doctrine/MetadataLoadInterceptor.php',
];
$ignoreErrors[] = [
	'message' => '#^Trait Go\\\\Proxy\\\\Part\\\\PropertyInterceptionTrait is used zero times and is not analysed\\.$#',
	'identifier' => 'trait.unused',
	'count' => 1,
	'path' => __DIR__ . '/src/Proxy/Part/PropertyInterceptionTrait.php',
];

// CachePathManager: the cache file loaded via `include` returns `mixed` at compile time.
// After is_array() narrowing, PHPStan gives array<mixed, mixed> (losing the string key type).
// The cache files are written by the framework itself (via var_export), so the shape is trusted.
$ignoreErrors[] = [
	'message' => '#^Property Go\\\\Instrument\\\\ClassLoading\\\\CachePathManager\\:\\:\\$cacheState \\(array\\<string, mixed\\>\\) does not accept array\\<mixed, mixed\\>\\.$#',
	'identifier' => 'assign.propertyType',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CachePathManager.php',
];
$ignoreErrors[] = [
	'message' => '#^Method Go\\\\Instrument\\\\ClassLoading\\\\CachePathManager\\:\\:queryCacheState\\(\\) should return array\\<string, mixed\\>\\|null but returns array\\<mixed, mixed\\>\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 2,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CachePathManager.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
