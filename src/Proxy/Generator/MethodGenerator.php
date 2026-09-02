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
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Generates a PHP class method declaration as an AST node or PHP string.
 *
 * Method bodies are stored as AST statements, enabling bidirectional conversion:
 *   - writing {@see self::$body} parses a PHP string into AST stmts
 *   - reading {@see self::$body} reconstructs the PHP string from AST stmts
 *   - {@see self::$stmts} for direct AST mutation
 */
final class MethodGenerator
{
    private static ?Standard $printer = null;
    private static ?Parser $parser    = null;
    private static ?BuilderFactory $factory = null;

    public Visibility $visibility = Visibility::PUBLIC;
    public bool $static     = false;
    public bool $final      = false;
    public bool $returnsRef = false;

    /** Marking a method abstract discards its body statements. */
    public bool $abstract = false {
        set {
            $this->abstract = $value;
            if ($value) {
                $this->stmts = null;
            }
        }
    }

    /** Marking a method as an interface member discards its body statements. */
    public bool $isInterface = false {
        set {
            $this->isInterface = $value;
            if ($value) {
                $this->stmts = null;
            }
        }
    }

    /** Return type; a type string (e.g. 'void', '?int') is normalized to a TypeGenerator on write. */
    public ?TypeGenerator $returnType = null {
        set(string|TypeGenerator|null $type) {
            $this->returnType = is_string($type) ? TypeGenerator::fromTypeString($type) : $type;
        }
    }

    public ?DocBlockGenerator $docBlock = null;

    /** @var ParameterGenerator[] */
    private array $parameters = [];

    /** @var \PhpParser\Node\AttributeGroup[] */
    private array $attributeGroups = [];

    /**
     * Underlying AST statements for direct traversal or mutation (null for abstract/interface methods).
     *
     * @var Stmt[]|null
     */
    public ?array $stmts = [];

    /**
     * Method body as a PHP string, backed by {@see self::$stmts}:
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
     * Creates a MethodGenerator from a reflection method.
     */
    public static function fromReflection(ReflectionMethod $method): self
    {
        $generator = new self($method->getName());

        $generator->visibility  = Visibility::fromReflectionMethod($method);
        $generator->static      = $method->isStatic();
        $generator->final       = $method->isFinal();
        $generator->abstract    = $method->isAbstract();
        $generator->returnsRef  = $method->returnsReference();
        $generator->isInterface = $method->getDeclaringClass()->isInterface();

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
                    $typeResolver = new TypeExpressionResolver(null, null);
                    $typeResolver->process($returnTypeNode, false);
                    $resolvedType = $typeResolver->getType();
                    if ($resolvedType !== null) {
                        $generator->returnType = TypeGenerator::fromReflectionType($resolvedType);
                    }
                }
            } else {
                $reflectionReturnType = $method->getReturnType();
                if ($reflectionReturnType instanceof ReflectionNamedType) {
                    $typeName = TypeGenerator::resolveReflectionNamedTypeName($reflectionReturnType);
                    $nullable = $reflectionReturnType->allowsNull() && !in_array($typeName, ['mixed', 'null'], true);
                    $generator->returnType = TypeGenerator::fromTypeString(($nullable ? '?' : '') . $typeName);
                } else {
                    $generator->returnType = TypeGenerator::fromReflectionType($reflectionReturnType);
                }
            }
        }

        // Docblock
        $docComment = $method->getDocComment();
        if ($docComment !== false) {
            $generator->docBlock = DocBlockGenerator::fromDocComment($docComment);
        }

        // Parameters
        foreach ($method->getParameters() as $reflectionParam) {
            $generator->addParameter(ParameterGenerator::fromReflection($reflectionParam));
        }

        // Attributes: cloned from the AST when available (parser-reflection), so that
        // argument expressions are never evaluated at weave time (issues #601, #602)
        $generator->attributeGroups = AttributeGroupsGenerator::fromReflector($method);

        return $generator;
    }

    public function addParameter(ParameterGenerator $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    /**
     * Returns the underlying AST ClassMethod node, ready for injection into a class.
     */
    public function getNode(): ClassMethod
    {
        $builder = self::getFactory()->method($this->name);

        match ($this->visibility) {
            Visibility::PUBLIC    => $builder->makePublic(),
            Visibility::PROTECTED => $builder->makeProtected(),
            Visibility::PRIVATE   => $builder->makePrivate(),
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

        foreach ($this->attributeGroups as $attrGroup) {
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
