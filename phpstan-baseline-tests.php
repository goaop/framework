<?php declare(strict_types = 1);

// Baseline for tests/ only (see issue #633). Keep src/ suppressions in
// phpstan-baseline.php — this file must never contain src/ paths.

$ignoreErrors = [];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
