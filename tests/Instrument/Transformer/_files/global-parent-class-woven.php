<?php
declare(strict_types=1);
namespace Test\ns1;

trait GlobalParentCollectionOriginalTrait
{
    public function hello(): string
    {
        return 'hello';
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/global-parent-class.php';
