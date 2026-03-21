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

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
