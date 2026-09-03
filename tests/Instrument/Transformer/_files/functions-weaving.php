<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * Namespace that calls global functions. When an advisor matches one of those global
 * functions, WeavingTransformer generates a namespaced function proxy into the
 * `_functions/` cache sub-directory and appends an include_once for it.
 */
function useGlobalFunctions(array $values): int
{
    return array_sum($values);
}
