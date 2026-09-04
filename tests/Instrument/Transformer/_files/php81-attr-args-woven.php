<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.0/8.1 — non-scalar and non-foldable attribute arguments on methods and
 * parameters. Weaving must copy the attribute argument expressions from the AST
 * verbatim: enum cases, new-in-initializer objects and global constants must
 * never be evaluated at weave time (see issues #601 and #602).
 * (Class-level attributes are tracked separately in issue #598.)
 */
enum AttrStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

#[\Attribute(\Attribute::TARGET_ALL)]
class RichValueAttr
{
    public function __construct(
        public mixed $value = null,
        public mixed $extra = null,
    ) {
    }
}

trait TestAttributeArgsClassOriginal
{
    #[RichValueAttr(AttrStatus::Disabled, PHP_INT_MAX)]
    public function tagged(#[RichValueAttr(AttrStatus::Active)] int $x = 8): int
    {
        return $x;
    }

    #[RichValueAttr(AttrStatus::Active, new \ArrayObject([1, 2]))]
    public function collected(): array
    {
        return [];
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php81-attr-args.php';
