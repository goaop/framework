<?php

use Go\Aop\Intercept\FieldAccessType;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;


$isAOPDisabled = isset($_COOKIE['aop_on']) && $_COOKIE['aop_on'] == 'false';
include __DIR__ . ($isAOPDisabled ? '/../vendor/autoload.php' : '/autoload_aspect.php');

class Example
{
    private bool $modified;

    public string $value = 'some' {
        get {
            return $this->joinpoint->__invoke($this, FieldAccessType::READ, $this->value);
        }
        set(string $value) {
            $this->value = $this->joinpoint->__invoke($this, FieldAccessType::WRITE, $this->value, $value);
        }
    }

    public function __construct()
    {
        $this->joinpoint = new \Go\Aop\Framework\ClassFieldAccess(
            [
                new \Go\Aop\Framework\BeforeInterceptor(function () {
                    echo "Before";
                })
            ],
            self::class,
            'value'
        );
    }

}
phpinfo();

$example = new Example();
$example->value = 'changed';
$test = $example->value;

$code = <<<'CODE'
<?php

class Foo {
    
    public function __construct(ReflectionFunctionAbstract $function, array $advices)
    {
        $accessor = function(array &$propertyStorage, object $target) {
            $propertyStorage = [
                'value' => &$target->value,
                'more' => &$target->more
            ];
            unset(
                $target->value,
                $target->more
            );
        };
        ($accessor->bindTo($this, parent::class))($this->__properties, $this);
        parent::__construct(...\array_slice([$a, $b], 0, \func_num_args()), ...$values);
    }
}
CODE;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
try {
    $ast = $parser->parse($code);
} catch (Error $error) {
    echo "Parse error: {$error->getMessage()}\n";
    return;
}

$dumper = new NodeDumper;
echo '<pre>', $dumper->dump($ast) . "\n";