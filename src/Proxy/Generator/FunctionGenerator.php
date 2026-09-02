<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * Generates a standalone PHP function declaration as an AST node or PHP string.
 *
 * Function bodies are stored as AST statements, enabling bidirectional conversion:
 *   - writing {@see self::$body} parses a PHP string into AST stmts
 *   - reading {@see self::$body} reconstructs the PHP string from AST stmts
 *   - {@see self::$stmts} for direct AST mutation
 */
final class FunctionGenerator
{
    private static ?Standard $printer      = null;
    private static ?Parser $parser         = null;
    private static ?BuilderFactory $factory = null;

    public bool $returnsRef = false;

    /** Return type; a type string (e.g. 'void', '?int') is normalized to a TypeGenerator on write. */
    public ?TypeGenerator $returnType = null {
        set(string|TypeGenerator|null $type) {
            $this->returnType = is_string($type) ? TypeGenerator::fromTypeString($type) : $type;
        }
    }

    public ?DocBlockGenerator $docBlock = null;

    /** @var ParameterGenerator[] */
    private array $parameters = [];

    /**
     * Underlying AST statements for direct traversal or mutation.
     *
     * @var Stmt[]
     */
    public array $stmts = [];

    /** @var \PhpParser\Node\AttributeGroup[] */
    private array $attributeGroups = [];

    /**
     * Function body as a PHP string, backed by {@see self::$stmts}:
     * writes are parsed into AST statements (no leading `<?php` needed),
     * reads reconstruct the PHP source from the stored AST statements.
     */
    public string $body {
        get {
            if (empty($this->stmts)) {
                return '';
            }
            return self::getPrinter()->prettyPrint($this->stmts);
        }
        set {
            if (trim($value) === '') {
                $this->stmts = [];
                return;
            }
            $ast = self::getParser()->parse('<?php ' . $value);
            $this->stmts = $ast ?? [];
        }
    }

    public function __construct(public readonly string $name)
    {
    }

    /**
     * Creates a FunctionGenerator from a reflection function.
     */
    public static function fromReflection(ReflectionFunction $function): self
    {
        $generator = new self($function->getShortName());

        $generator->returnsRef = $function->returnsReference();

        // Return type
        if ($function->hasReturnType()) {
            $reflectionReturnType = $function->getReturnType();
            if ($reflectionReturnType instanceof ReflectionNamedType) {
                $typeName = TypeGenerator::resolveReflectionNamedTypeName($reflectionReturnType);
                $nullable = $reflectionReturnType->allowsNull() && !in_array($typeName, ['mixed', 'null'], true);
                $generator->returnType = TypeGenerator::fromTypeString(($nullable ? '?' : '') . $typeName);
            } else {
                $generator->returnType = TypeGenerator::fromReflectionType($reflectionReturnType);
            }
        }

        // Docblock
        $docComment = $function->getDocComment();
        if ($docComment !== false) {
            $generator->docBlock = DocBlockGenerator::fromDocComment($docComment);
        }

        // Parameters
        foreach ($function->getParameters() as $reflectionParam) {
            $generator->addParameter(ParameterGenerator::fromReflection($reflectionParam));
        }

        // Attributes: cloned from the AST when available (parser-reflection), so that
        // argument expressions are never evaluated at weave time (issues #601, #602)
        $generator->attributeGroups = AttributeGroupsGenerator::fromReflector($function);

        return $generator;
    }

    public function addParameter(ParameterGenerator $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    /**
     * Returns the underlying AST Function_ node, ready for injection into a file.
     */
    public function getNode(): Function_
    {
        $builder = self::getFactory()->function($this->name);

        if ($this->returnsRef) {
            $builder->makeReturnByRef();
        }
        if ($this->returnType !== null) {
            $builder->setReturnType($this->returnType->getNode());
        }

        foreach ($this->parameters as $param) {
            $builder->addParam($param->getNode());
        }

        foreach ($this->attributeGroups as $attrGroup) {
            $builder->addAttribute($attrGroup);
        }

        foreach ($this->stmts as $stmt) {
            $builder->addStmt($stmt);
        }

        $node = $builder->getNode();

        if ($this->docBlock !== null) {
            $node->setAttribute('comments', [
                new \PhpParser\Comment\Doc($this->docBlock->generate()),
            ]);
        }

        return $node;
    }

    /**
     * Generates the PHP function declaration as a string.
     */
    public function generate(): string
    {
        return self::getPrinter()->prettyPrint([$this->getNode()]);
    }

    private static function getPrinter(): Standard
    {
        if (self::$printer === null) {
            self::$printer = new GeneratedCodePrinter(['shortArraySyntax' => true]);
        }
        return self::$printer;
    }

    private static function getParser(): Parser
    {
        if (self::$parser === null) {
            self::$parser = (new ParserFactory())->createForNewestSupportedVersion();
        }
        return self::$parser;
    }

    private static function getFactory(): BuilderFactory
    {
        if (self::$factory === null) {
            self::$factory = new BuilderFactory();
        }
        return self::$factory;
    }
}
