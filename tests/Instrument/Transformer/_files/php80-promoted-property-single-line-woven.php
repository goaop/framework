<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Class with a promoted constructor property in a single-line constructor used for
 * testing interception of promoted properties (issue #599).
 */
trait SingleLinePromotedClass__AopProxied
{
    public function __construct(string $tag = 'default') { $this->tag = $tag;}
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-promoted-property-single-line.php';
