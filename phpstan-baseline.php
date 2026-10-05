<?php declare(strict_types = 1);

$ignoreErrors = [];

// CachePathManager: the cache file loaded via `include` returns `mixed` at compile time.
// After is_array() narrowing, PHPStan gives array<mixed, mixed> (losing the string key type).
// The cache files are written by the framework itself (via var_export), so the shape is trusted.
$ignoreErrors[] = [
	'message' => '#^Method Go\\\\Instrument\\\\ClassLoading\\\\CachePathManager\\:\\:queryCacheState\\(\\) should return array\\<string, mixed\\>\\|null but returns array\\<mixed, mixed\\>\\|null\\.$#',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Instrument/ClassLoading/CachePathManager.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
