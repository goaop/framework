<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.1 backed enum whose case values are constant expressions, not plain literals (issue #600).
 */
trait ConstExprStatusOriginal 
{
    private const int SHIFT = 2;

    
    
    

    public function describe(): string
    {
        return $this->name . '=' . $this->value;
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php81-enum-const-expr.php';
