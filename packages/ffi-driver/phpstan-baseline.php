<?php

declare(strict_types=1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'message' => '#^Go\\\\Driver\\\\Ffi\\\\DonorTraitGenerator\\:\\:__construct\\(\\) does not call parent constructor from Go\\\\Proxy\\\\ClassProxyGenerator\\.$#',
    'identifier' => 'constructor.missingParentCall',
    'count' => 1,
    'path' => __DIR__ . '/src/DonorTraitGenerator.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
