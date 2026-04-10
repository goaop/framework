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

use Go\ParserReflection\Resolver\TypeExpressionResolver;
use PhpParser\BuilderFactory;
use PhpParser\Modifiers;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Generates a PHP class method declaration as an AST node or PHP string.
 *
 * Method bodies are stored as AST statements, enabling bidirectional conversion:
 *   - {@see setBody()} parses a PHP string into AST stmts
 *   - {@see getBody()} reconstructs the PHP string from AST stmts
 *   - {@see setStmts()} / {@see getStmts()} for direct AST mutation
 */
final class MethodGenerator
{
    public const VISIBILITY_PUBLIC    = 'public';
    public const VISIBILITY_PROTECTED = 'protected';
    public const VISIBILITY_PRIVATE   = 'private';

    private static ?Standard $printer = null;
    private static ?Parser $parser    = null;
    private static ?BuilderFactory $factory = null;

    private string $name;
    private string $visibility = self::VISIBILITY_PUBLIC;
    private bool $static       = false;
    private bool $final        = false;
    private bool $abstract     = false;
    private bool $returnsRef   = false;
    private bool $isInterface  = false;
    private ?TypeGenerator $returnType = null;
    private ?DocBlockGenerator $docBlock = null;

    /** @var ParameterGenerator[] */
    private array $parameters = [];

    /** @var ReflectionAttribute<object>[] */
    private array $reflectionAttributes = [];

    /** @var Stmt[]|null null for abstract methods */
    private ?array $stmts = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Creates a MethodGenerator from a reflection method.
     *
     * @param bool $useWidening When true, parameter types are omitted
     */
    public static function fromReflection(ReflectionMethod $method, bool $useWidening = false): self
    {
        $generator = new self($method->getName());

        // Visibility
        if ($method->isPrivate()) {
            $generator->setVisibility(self::VISIBILITY_PRIVATE);
        } elseif ($method->isProtected()) {
            $generator->setVisibility(self::VISIBILITY_PROTECTED);
        } else {
            $generator->setVisibility(self::VISIBILITY_PUBLIC);
        }

        $generator->setStatic($method->isStatic());
        $generator->setFinal($method->isFinal());
        $generator->setAbstract($method->isAbstract());
        $generator->setReturnsReference($method->returnsReference());
        $generator->setInterface($method->getDeclaringClass()->isInterface());

        // Return type
        if ($method->hasReturnType()) {
            // If the method exposes its AST node (Go\ParserReflection\ReflectionMethod),
            // re-process the raw type node with TypeExpressionResolver(null, null) so that
            // 'self' and 'parent' keywords are preserved without PHP 8.5+ name resolution,
            // while regular class names are still fully qualified via resolvedName attributes.
            if (method_exists($method, 'getNode')) {
                /** @var ClassMethod $astMethod */
                $astMethod = $method->getNode();
                $returnTypeNode = $astMethod->returnType;
                if ($returnTypeNode !== null) {
                    $typeResolver = new TypeExpressionResolver();
                    $typeResolver->process($returnTypeNode, false);
                    $resolvedType = $typeResolver->getType();
                    if ($resolvedType !== null) {
                        $generator->setReturnType(TypeGenerator::fromReflectionType($resolvedType));
                    }
                }
            } else {
                $reflectionReturnType = $method->getReturnType();
                if ($reflectionReturnType instanceof ReflectionNamedType) {
                    $typeName = TypeGenerator::resolveReflectionNamedTypeName($reflectionReturnType);
                    $nullable = $reflectionReturnType->allowsNull() && !in_array($typeName, ['mixed', 'null'], true);
                    $generator->setReturnType(TypeGenerator::fromTypeString(($nullable ? '?' : '') . $typeName));
                } else {
                    $generator->setReturnType(TypeGenerator::fromReflectionType($reflectionReturnType));
                }
            }
        }

        // Docblock
        $docComment = $method->getDocComment();
        if ($docComment !== false) {
            $generator->setDocBlock(DocBlockGenerator::fromDocComment($docComment));
        }

        // Parameters
        foreach ($method->getParameters() as $reflectionParam) {
            $generator->addParameter(ParameterGenerator::fromReflection($reflectionParam, $useWidening));
        }

        // Attributes
        $generator->reflectionAttributes = $method->getAttributes();

        return $generator;
    }

    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function setStatic(bool $static): void
    {
        $this->static = $static;
    }

    public function setFinal(bool $final): void
    {
        $this->final = $final;
    }

    public function setAbstract(bool $abstract): void
    {
        $this->abstract = $abstract;
        if ($abstract) {
            $this->stmts = null;
        }
    }

    public function setReturnsReference(bool $returnsRef): void
    {
        $this->returnsRef = $returnsRef;
    }

    public function setInterface(bool $isInterface): void
    {
        $this->isInterface = $isInterface;
        if ($isInterface) {
            $this->stmts = null;
        }
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
     * Sets the method body from a PHP string.
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
     * Reconstructs the method body as a PHP string from the stored AST statements.
     */
    public function getBody(): string
    {
        if (empty($this->stmts)) {
            return '';
        }
        return self::getPrinter()->prettyPrint($this->stmts);
    }

    /**
     * Replaces method body statements directly with pre-built AST nodes.
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
     * @return Stmt[]|null null for abstract/interface methods
     */
    public function getStmts(): ?array
    {
        return $this->stmts;
    }

    /**
     * Returns the method name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the underlying AST ClassMethod node, ready for injection into a class.
     */
    public function getNode(): ClassMethod
    {
        $builder = self::getFactory()->method($this->name);

        match ($this->visibility) {
            self::VISIBILITY_PUBLIC    => $builder->makePublic(),
            self::VISIBILITY_PROTECTED => $builder->makeProtected(),
            self::VISIBILITY_PRIVATE   => $builder->makePrivate(),
            default                    => $builder->makePublic(),
        };

        if ($this->static) {
            $builder->makeStatic();
        }
        if ($this->final) {
            $builder->makeFinal();
        }
        if ($this->abstract) {
            $builder->makeAbstract();
        }
        if ($this->returnsRef) {
            $builder->makeReturnByRef();
        }
        if ($this->returnType !== null) {
            $builder->setReturnType($this->returnType->getNode());
        }

        foreach ($this->parameters as $param) {
            $builder->addParam($param->getNode());
        }

        foreach (AttributeGroupsGenerator::fromReflectionAttributes($this->reflectionAttributes) as $attrGroup) {
            $builder->addAttribute($attrGroup);
        }

        if (!$this->abstract && !$this->isInterface && $this->stmts !== null) {
            foreach ($this->stmts as $stmt) {
                $builder->addStmt($stmt);
            }
        }

        $node = $builder->getNode();

        // Attach docblock as a comment
        if ($this->docBlock !== null) {
            $node->setAttribute('comments', [
                new \PhpParser\Comment\Doc($this->docBlock->generate()),
            ]);
        }

        return $node;
    }

    /**
     * Generates the PHP method declaration as a string.
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
