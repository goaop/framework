<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
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
 *   - {@see setBody()} parses a PHP string into AST stmts
 *   - {@see getBody()} reconstructs the PHP string from AST stmts
 *   - {@see setStmts()} / {@see getStmts()} for direct AST mutation
 */
final class FunctionGenerator
{
    private static ?Standard $printer      = null;
    private static ?Parser $parser         = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private bool $returnsRef = false;
    private ?TypeGenerator $returnType = null;
    private ?DocBlockGenerator $docBlock = null;

    /** @var ParameterGenerator[] */
    private array $parameters = [];

    /** @var Stmt[] */
    private array $stmts = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Creates a FunctionGenerator from a reflection function.
     *
     * @param bool $useWidening When true, parameter types are omitted
     */
    public static function fromReflection(ReflectionFunction $function, bool $useWidening = false): self
    {
        $generator = new self($function->getShortName());

        $generator->setReturnsReference($function->returnsReference());

        // Return type
        if ($function->hasReturnType()) {
            $reflectionReturnType = $function->getReturnType();
            if ($reflectionReturnType instanceof ReflectionNamedType) {
                $typeName = $reflectionReturnType->getName();
                $nullable = $reflectionReturnType->allowsNull() && !in_array($typeName, ['mixed', 'null'], true);
                $generator->setReturnType(TypeGenerator::fromTypeString(($nullable ? '?' : '') . $typeName));
            } else {
                $generator->setReturnType(TypeGenerator::fromReflectionType($reflectionReturnType));
            }
        }

        // Docblock
        $docComment = $function->getDocComment();
        if ($docComment !== false) {
            $generator->setDocBlock(DocBlockGenerator::fromDocComment($docComment));
        }

        // Parameters
        foreach ($function->getParameters() as $reflectionParam) {
            $generator->addParameter(ParameterGenerator::fromReflection($reflectionParam, $useWidening));
        }

        return $generator;
    }

    public function setReturnsReference(bool $returnsRef): void
    {
        $this->returnsRef = $returnsRef;
    }

    public function setReturnType(string|TypeGenerator $type): void
    {
        if (is_string($type)) {
            $type = TypeGenerator::fromTypeString($type);
        }
        $this->returnType = $type;
    }

    public function setDocBlock(DocBlockGenerator $docBlock): void
    {
        $this->docBlock = $docBlock;
    }

    public function addParameter(ParameterGenerator $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    /**
     * Sets the function body from a PHP string.
     * The string is parsed into AST statements (no leading `<?php` needed).
     */
    public function setBody(string $rawPhp): void
    {
        if (trim($rawPhp) === '') {
            $this->stmts = [];
            return;
        }
        $ast = self::getParser()->parse('<?php ' . $rawPhp);
        $this->stmts = $ast ?? [];
    }

    /**
     * Reconstructs the function body as a PHP string from the stored AST statements.
     */
    public function getBody(): string
    {
        if (empty($this->stmts)) {
            return '';
        }
        return self::getPrinter()->prettyPrint($this->stmts);
    }

    /**
     * Replaces function body statements with pre-built AST nodes.
     *
     * @param Stmt[] $stmts
     */
    public function setStmts(array $stmts): void
    {
        $this->stmts = $stmts;
    }

    /**
     * Returns the underlying AST statements for direct traversal or mutation.
     *
     * @return Stmt[]
     */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    /**
     * Returns the function name.
     */
    public function getName(): string
    {
        return $this->name;
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
            self::$printer = new Standard(['shortArraySyntax' => true]);
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
