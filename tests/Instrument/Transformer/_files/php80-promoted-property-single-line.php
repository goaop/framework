<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Class with a promoted constructor property in a single-line constructor used for
 * testing interception of promoted properties (issue #599).
 */
class SingleLinePromotedClass
{
    public function __construct(public string $tag = 'default') {}
}
