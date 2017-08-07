<?php
declare(strict_types = 1);

namespace Go\Instrument\Transformer;

class ConstructorExecutionTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConstructorExecutionTransformer
     */
    protected static $transformer;

    /**
     * @var StreamMetaData|null
     */
    protected $metadata = null;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        if (!self::$transformer) {
            self::$transformer = new ConstructorExecutionTransformer();
        }
        $stream = fopen('php://input', 'r');
        $this->metadata = new StreamMetaData($stream);
        fclose($stream);
    }

    /**
     * @dataProvider listOfExpressions
     */
    public function testCanTransformNewExpressions($source, $expected)
    {
        $this->metadata->source = "<?php $source; ?>";
        self::$transformer->transform($this->metadata);
        $output = "<?php $expected; ?>";
        $this->assertEquals($output, $this->metadata->source);
    }

    public function listOfExpressions()
    {
        return [
            [
                '$a = new stdClass',
                '$a = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ stdClass::class}'
            ],
            [
                '$b = new stdClass()',
                '$b = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ stdClass::class}()'
            ],
            [
                '$stdClass = "stdClass"; $c = new $stdClass',
                '$stdClass = "stdClass"; $c = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ $stdClass}'
            ],
            [
                '$stdClass = "stdClass"; $d = new $stdClass()',
                '$stdClass = "stdClass"; $d = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ $stdClass}()'
            ],
            [
                '$e = new \Exception',
                '$e = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ \Exception::class}'
            ],
            [
                '$f = new \Exception("Test")',
                '$f = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ \Exception::class}("Test")'
            ],
            [
                '$g = new self',
                '$g = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ self::class}',
            ],
            [
                '$h = new static()',
                '$h = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ static::class}()'
            ],
            [
                '$j = new self::$stdClass()',
                '$j = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ self::$stdClass}()'
            ],
            [
                '$k = new static::$exception["Exception"]("Test")',
                '$k = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ static::$exception["Exception"]}("Test")'
            ],
            [
                '$l = new self::$object[0]->name("Test Message")',
                '$l = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ self::$object[0]->name}("Test Message")'
            ],
            [
                '$m = new static::$object[0]->name',
                '$m = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ static::$object[0]->name}'
            ],
            [
                '$n = new stdClass(new static::$object[0]->name)',
                '$n = \Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ stdClass::class}(\Go\Instrument\Transformer\ConstructorExecutionTransformer::getInstance()->{ static::$object[0]->name})'
            ]
        ];
    }
}
