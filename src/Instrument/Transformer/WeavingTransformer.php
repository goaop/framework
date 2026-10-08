<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoaderInterface;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionFile;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\ParserReflection\ReflectionMethod;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\EnumProxyGenerator;
use Go\Proxy\FunctionProxyGenerator;
use Go\Proxy\TraitProxyGenerator;
use Closure;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use ReflectionProperty;
use Throwable;

/**
 * Main transformer that performs weaving of aspects into the source code
 */
final class WeavingTransformer extends BaseSourceTransformer
{
    private const string FUNCTIONS_CACHE_SUFFIX = '/_functions/';

    /**
     * Class-level attributes that are compile-time invalid on traits.
     *
     * When a class is converted to a trait, these attribute entries must be removed from the
     * woven trait tokens: PHP raises "Cannot apply #[\Attribute] to trait" (and the same for
     * #[\AllowDynamicProperties]) at load time. The proxy class re-declares them from the AST
     * via AttributeGroupsGenerator, so attribute classes keep working (issue #615).
     *
     * @var list<string>
     */
    private const array TRAIT_INCOMPATIBLE_ATTRIBUTES = ['Attribute', 'AllowDynamicProperties'];

    /**
     * Constructs a weaving transformer
     *
     * @param AdviceMatcherInterface $adviceMatcher    Advice matcher for class
     * @param CachePathManager       $cachePathManager Cache manager
     * @param AspectLoaderInterface  $aspectLoader     Loader for aspects
     */
    public function __construct(
        AspectKernel $kernel,
        protected readonly AdviceMatcherInterface $adviceMatcher,
        private readonly CachePathManager $cachePathManager,
        protected readonly AspectLoaderInterface $aspectLoader,
    ) {
        parent::__construct($kernel);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     */
    public function transform(StreamMetaData $metadata): TransformerResult
    {
        $totalTransformations = 0;
        $parsedSource         = new ReflectionFile($metadata->uri, $metadata->syntaxTree);

        // Check if we have some new aspects that weren't loaded yet
        $unloadedAspects = $this->aspectLoader->getUnloadedAspects();
        if (!empty($unloadedAspects)) {
            $this->loadAndRegisterAspects($unloadedAspects);
        }
        $advisors = $this->container->getServicesByInterface(Advisor::class);

        $namespaces = $parsedSource->getFileNamespaces();

        foreach ($namespaces as $namespace) {
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {
                // Every discovered class (woven or not) is recorded so the runtime class
                // map / skip set can be built for the autoloader at flush time
                $this->cachePathManager->registerClassForResource($metadata->uri, $class->getName());

                // Skip interfaces — enums are supported via EnumProxyGenerator. Aspects are skipped
                // in processSingleClass() once advices were found: the check reflects every ancestor
                if ($class->isInterface()) {
                    continue;
                }
                $wasClassProcessed = $this->processSingleClass(
                    $advisors,
                    $metadata,
                    $class,
                    $namespace,
                    $parsedSource->isStrictMode(),
                );
                $totalTransformations += (int) $wasClassProcessed;
            }
            $wasFunctionsProcessed = $this->processFunctions($advisors, $metadata, $namespace);
            $totalTransformations += (int) $wasFunctionsProcessed;
        }

        $result = ($totalTransformations > 0) ? TransformerResult::Transformed : TransformerResult::Abstain;

        return $result;
    }

    /**
     * Performs weaving of single class if needed, returns true if the class was processed
     *
     * @param Advisor[]               $advisors      List of advisors
     * @param StreamMetaData          $metadata
     * @param ReflectionClass         $class
     * @param ReflectionFileNamespace $namespace     Namespace block of the file that declares the class
     * @param bool                    $useStrictMode If the source file used strict mode, the proxy should too
     * @return bool
     */
    private function processSingleClass(
        array $advisors,
        StreamMetaData $metadata,
        ReflectionClass $class,
        ReflectionFileNamespace $namespace,
        bool $useStrictMode,
    ): bool {
        try {
            $advices = $this->adviceMatcher->getAdvicesForClass($class, $advisors);
        } catch (Throwable $matchingError) {
            // Aspects used to be skipped before matching, so matching one must not fail the file
            if ($this->isAspectSafe($class)) {
                return false;
            }
            throw $matchingError;
        }

        if (empty($advices)) {
            // Fast return if there aren't any advices for that class. It comes before the aspect check,
            // as that one reflects (and parses) every ancestor and fails when one can't be located
            return false;
        }

        // Aspects are never woven
        if ($class->implementsInterface(Aspect::class)) {
            return false;
        }

        // Sort advices in advance to keep the correct order in cache, and leave only keys for the cache
        $advices = AbstractJoinpoint::flatAndSortAdvices($advices);

        // Prepare new class name
        $newClassName = $class->getShortName() . AspectContainer::ORIGINAL_TRAIT_SUFFIX;
        $newFqcn      = ($class->getNamespaceName() !== '' ? $class->getNamespaceName() . '\\' : '') . $newClassName;

        // Imports of the namespace block declaring the class are copied into the proxy (parameter defaults
        // and types rely on them); the generators reserve their names and alias their own imports around them.
        // They are read from the block being woven: a lookup by file and namespace name would re-parse the file
        // once its syntax tree is evicted from the parser-reflection cache, and would return the first block
        // when the file declares the same namespace several times
        $originalImports = $namespace->getNamespaceAliases();
        // Intercepted methods are looked up by name several times per method, here and in the proxy generators
        $methods = ClassProxyGenerator::indexMethods($class);

        // For traits: rename the trait (legacy approach, TraitProxyGenerator generates a child trait).
        // For enums: convert the enum body to a trait (cases extracted to proxy enum by EnumProxyGenerator).
        // For classes: convert the class body to a trait (new trait-based engine).
        if ($class->isTrait()) {
            $this->removeInterceptedPropertiesFromTraitBody($class, $advices, $metadata);
            $this->adjustOriginalTrait($class, $metadata, $newClassName);
            $childProxyGenerator = new TraitProxyGenerator($class, $newFqcn, $advices, $originalImports, $methods);
        } elseif ($class->isEnum()) {
            $this->convertEnumToTrait($class, $advices, $methods, $metadata, $newClassName);
            $childProxyGenerator = new EnumProxyGenerator($class, $newFqcn, $advices, $originalImports, $methods);
        } else {
            $this->convertClassToTrait($class, $advices, $methods, $metadata, $newClassName);
            $childProxyGenerator = new ClassProxyGenerator($class, $newFqcn, $advices, $originalImports, $methods);
        }

        $childCode = $childProxyGenerator->generate();

        if ($useStrictMode) {
            $childCode = 'declare(strict_types=1);' . PHP_EOL . $childCode;
        }

        $contentToInclude = $this->saveProxyToCache($class, $childCode);

        // Get last token for this class
        $classNode = $class->getNode();
        $lastClassToken = $classNode->getAttribute('endTokenPos');
        if (!is_int($lastClassToken)) {
            return false;
        }

        $metadata->tokenStream[$lastClassToken]->text .= PHP_EOL . $contentToInclude;

        return true;
    }

    /**
     * Checks whether the class is an aspect, a failure of the check counts as "not an aspect"
     */
    private function isAspectSafe(ReflectionClass $class): bool
    {
        try {
            return $class->implementsInterface(Aspect::class);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Adjust definition of original trait source to enable extending
     */
    private function adjustOriginalTrait(
        ReflectionClass $class,
        StreamMetaData $streamMetaData,
        string $newClassName,
    ): void {
        [$position, $lastPosition] = $this->getDeclarationTokenRange($class->getNode());
        while ($position <= $lastPosition) {
            if (isset($streamMetaData->tokenStream[$position])) {
                $token = $streamMetaData->tokenStream[$position];
                // First string is class/trait name
                if ($token->id === T_STRING) {
                    $streamMetaData->tokenStream[$position]->text = $newClassName;
                    // We have finished our job, can break this loop
                    break;
                }
            }
            ++$position;
        }
    }

    /**
     * Returns the token range where the class/enum declaration scan should start and end.
     *
     * A ClassLike node's startTokenPos includes its attribute groups (`#[...]`), so scanning
     * from there would rename the first T_STRING inside the attribute to the trait name and
     * then delete the real class header (see https://github.com/goaop/framework/issues/598).
     * Class-level attributes are kept as-is on the generated trait — attributes are legal
     * on traits — so the scan starts right after the last attribute group.
     *
     * Every scan stays within the declaration: a malformed token stream can never loop forever.
     *
     * @return array{int, int} Positions of the first and the last token to scan
     *
     * @throws WeavingException When the node was parsed without token positions
     */
    private function getDeclarationTokenRange(ClassLike $classNode): array
    {
        $position     = $classNode->getAttribute('startTokenPos');
        $lastPosition = $classNode->getAttribute('endTokenPos');
        if (!is_int($position) || !is_int($lastPosition)) {
            throw new WeavingException("Declaration of {$classNode->name} has no token positions to weave");
        }
        $lastAttrGroup = end($classNode->attrGroups);
        if ($lastAttrGroup !== false) {
            $attrGroupsEnd = $lastAttrGroup->getAttribute('endTokenPos');
            if (is_int($attrGroupsEnd)) {
                $position = $attrGroupsEnd + 1;
            }
        }

        return [$position, $lastPosition];
    }

    /**
     * Convert a regular class declaration into a trait for the trait-based AOP engine.
     *
     * Performs the following token-stream modifications in-place:
     *  - Removes 'final' and 'abstract' modifiers from the class keyword
     *  - Changes 'class' keyword text to 'trait'
     *  - Renames the class to $newClassName (OriginalTrait suffix)
     *  - Removes the 'extends X' and 'implements Y, Z' clauses (moved to the proxy class)
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices List of class advices
     * @param array<string, \ReflectionMethod> $methods Methods of the class by name
     */
    private function convertClassToTrait(
        ReflectionClass $class,
        array $advices,
        array $methods,
        StreamMetaData $streamMetaData,
        string $newClassName,
    ): void {
        $classNode = $class->getNode();
        [$position, $lastPosition] = $this->getDeclarationTokenRange($classNode);

        $classNameFound = false;

        while ($position <= $lastPosition) {
            if (!isset($streamMetaData->tokenStream[$position])) {
                ++$position;
                continue;
            }

            $token = $streamMetaData->tokenStream[$position];

            if (!$classNameFound) {
                // Remove 'final' modifier (and trailing whitespace) — traits cannot be final
                if ($token->id === T_FINAL) {
                    $this->removeModifierToken($position, $streamMetaData);
                    ++$position;
                    continue;
                }
                // Remove 'abstract' modifier (and trailing whitespace) — trait keyword itself has no modifier
                if ($token->id === T_ABSTRACT) {
                    $this->removeModifierToken($position, $streamMetaData);
                    ++$position;
                    continue;
                }
                // Remove 'readonly' modifier — traits cannot be readonly
                if ($token->id === T_READONLY) {
                    $this->removeModifierToken($position, $streamMetaData);
                    ++$position;
                    continue;
                }
                // Rewrite 'class' keyword to 'trait'
                if ($token->id === T_CLASS) {
                    $streamMetaData->tokenStream[$position]->text = 'trait';
                    ++$position;
                    continue;
                }
                // First T_STRING after the keyword is the class name — rename it
                if ($token->id === T_STRING) {
                    $streamMetaData->tokenStream[$position]->text = $newClassName;
                    $classNameFound = true;
                    ++$position;
                    continue;
                }
            } else {
                // After the class name: strip 'extends X implements Y, Z' up to the opening '{'
                if ($token->text === '{') {
                    break;
                }
                // Keep whitespace tokens to preserve original brace placement (same line or next line)
                if ($token->id !== T_WHITESPACE) {
                    $this->blankTokenRangePreservingNewlines($position, $position, $streamMetaData);
                }
            }

            ++$position;
        }

        // Strip #[\Override] from intercepted methods.
        // PHP copies attributes to alias names (e.g. fooOriginalAlias). Since fooOriginalAlias has no parent
        // match, PHP would raise a fatal error if #[\Override] were present on the alias.
        $this->removeInterceptedPropertiesFromTraitBody($class, $advices, $streamMetaData);
        $this->stripOverrideAttributeFromInterceptedMethods($class, $advices, $methods, $streamMetaData);
        $this->stripTraitIncompatibleClassAttributes($classNode, $streamMetaData);
    }

    /**
     * Removes class-level attributes that cannot be applied to traits (issue #615).
     *
     * `#[\Attribute]` and `#[\AllowDynamicProperties]` are compile-time invalid on traits, so
     * weaving an attribute class would make the woven trait fatal at load time. The attribute
     * entries are removed from the trait tokens only — the proxy class copies the original
     * attribute groups from the AST (AttributeGroupsGenerator), so runtime reflection on the
     * proxied class still reports them.
     *
     * In a multi-attribute group (e.g. `#[\Attribute, SomethingElse]`) only the incompatible
     * entries are removed together with one adjacent comma; the rest of the group is kept.
     * Newlines inside removed token ranges are preserved so that all subsequent declarations
     * stay at their original line numbers (XDebug breakpoint mapping).
     */
    private function stripTraitIncompatibleClassAttributes(ClassLike $classNode, StreamMetaData $streamMetaData): void
    {
        $this->stripAttributes(
            $classNode->attrGroups,
            static fn(Attribute $attribute): bool => in_array(self::resolveAttributeName($attribute), self::TRAIT_INCOMPATIBLE_ATTRIBUTES, true),
            $streamMetaData,
        );
    }

    /**
     * Removes the attributes selected by $shouldRemove from the given attribute groups in the token stream
     *
     * A group whose attributes are all removed is blanked out entirely; otherwise only the selected entries are
     * removed together with one adjacent comma. Newlines inside the removed token ranges are kept, so every
     * following declaration stays at its original line number (XDebug breakpoint mapping).
     *
     * @param AttributeGroup[]              $attrGroups
     * @param Closure(Attribute): bool      $shouldRemove
     */
    private function stripAttributes(array $attrGroups, Closure $shouldRemove, StreamMetaData $streamMetaData): void
    {
        foreach ($attrGroups as $attrGroup) {
            $attributesToRemove = array_filter($attrGroup->attrs, $shouldRemove);
            if ($attributesToRemove === []) {
                continue;
            }
            if (count($attributesToRemove) === count($attrGroup->attrs)) {
                $start = $attrGroup->getAttribute('startTokenPos');
                $end   = $attrGroup->getAttribute('endTokenPos');
                if (is_int($start) && is_int($end)) {
                    $this->blankTokenRangePreservingNewlines($start, $end, $streamMetaData);
                }
                continue;
            }
            foreach ($attributesToRemove as $attribute) {
                $start = $attribute->getAttribute('startTokenPos');
                $end   = $attribute->getAttribute('endTokenPos');
                if (!is_int($start) || !is_int($end)) {
                    continue;
                }
                $this->blankTokenRangePreservingNewlines($start, $end, $streamMetaData);
                $this->removeAdjacentAttributeComma($start, $end, $streamMetaData);
            }
        }
    }

    /**
     * Returns the fully qualified name of an attribute class, without a leading backslash
     *
     * parser-reflection runs the NameResolver without replacing nodes, so the resolved name is kept in the
     * `resolvedName` attribute of the name node; the name as written is the fallback.
     */
    private static function resolveAttributeName(Attribute $attribute): string
    {
        $resolvedName = $attribute->name->getAttribute('resolvedName');
        $name         = $resolvedName instanceof Name ? $resolvedName : $attribute->name;

        return ltrim($name->toString(), '\\');
    }

    /**
     * Removes a class modifier token (final, abstract, readonly) together with the whitespace after it
     *
     * The whitespace is removed only when it holds no newline, so `final\nclass Foo` keeps its line count.
     */
    private function removeModifierToken(int $position, StreamMetaData $streamMetaData): void
    {
        unset($streamMetaData->tokenStream[$position]);
        $nextToken = $streamMetaData->tokenStream[$position + 1] ?? null;
        if ($nextToken !== null && $nextToken->id === T_WHITESPACE && strpbrk($nextToken->text, "\r\n") === false) {
            unset($streamMetaData->tokenStream[$position + 1]);
        }
    }

    /**
     * Blanks out all tokens in [$start, $end], keeping only the newlines they contained.
     *
     * Token objects are kept in place (text emptied) instead of being unset, so the iteration
     * order of the token stream is untouched and the line budget of the file is preserved.
     */
    private function blankTokenRangePreservingNewlines(int $start, int $end, StreamMetaData $streamMetaData): void
    {
        for ($position = $start; $position <= $end; ++$position) {
            if (!isset($streamMetaData->tokenStream[$position])) {
                continue;
            }
            $text = $streamMetaData->tokenStream[$position]->text;
            $streamMetaData->tokenStream[$position]->text = str_repeat("\n", substr_count($text, "\n"));
        }
    }

    /**
     * Removes one comma adjacent to a removed attribute entry inside a multi-attribute group.
     *
     * Prefers the trailing comma (after $end); falls back to the leading comma (before $start)
     * when the removed entry was the last one in the group. Whitespace next to the comma is
     * dropped only when it holds no newline (line budget).
     */
    private function removeAdjacentAttributeComma(int $start, int $end, StreamMetaData $streamMetaData): void
    {
        // Scan forward for a trailing comma, skipping blank/whitespace tokens
        $position = $end + 1;
        while (isset($streamMetaData->tokenStream[$position])) {
            $token = $streamMetaData->tokenStream[$position];
            if ($token->text === ',') {
                unset($streamMetaData->tokenStream[$position]);
                $nextPosition = $position + 1;
                if (isset($streamMetaData->tokenStream[$nextPosition])) {
                    $nextToken = $streamMetaData->tokenStream[$nextPosition];
                    if ($nextToken->id === T_WHITESPACE && strpbrk($nextToken->text, "\r\n") === false) {
                        unset($streamMetaData->tokenStream[$nextPosition]);
                    }
                }

                return;
            }
            if ($token->id !== T_WHITESPACE && $token->text !== '') {
                break;
            }
            ++$position;
        }
        // No trailing comma — remove the leading one instead
        $position = $start - 1;
        while (isset($streamMetaData->tokenStream[$position])) {
            $token = $streamMetaData->tokenStream[$position];
            if ($token->text === ',') {
                unset($streamMetaData->tokenStream[$position]);

                return;
            }
            if ($token->id !== T_WHITESPACE && $token->text !== '') {
                break;
            }
            --$position;
        }
    }

    /**
     * Convert an enum declaration into a trait for the trait-based AOP engine.
     *
     * Performs the following token-stream modifications in-place:
     *  - Changes 'enum' keyword text to 'trait'
     *  - Renames the enum to $newClassName (OriginalTrait suffix)
     *  - Removes the backed type (': string' / ': int') and any 'implements ...' clause
     *  - Removes all enum case declarations from the body (cases live in the proxy enum instead)
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices List of class advices
     * @param array<string, \ReflectionMethod> $methods Methods of the enum by name
     */
    private function convertEnumToTrait(
        ReflectionClass $class,
        array $advices,
        array $methods,
        StreamMetaData $streamMetaData,
        string $newClassName,
    ): void {
        $classNode = $class->getNode();
        [$position, $lastPosition] = $this->getDeclarationTokenRange($classNode);

        $classNameFound = false;

        while ($position <= $lastPosition) {
            if (!isset($streamMetaData->tokenStream[$position])) {
                ++$position;
                continue;
            }

            $token = $streamMetaData->tokenStream[$position];

            if (!$classNameFound) {
                // Rewrite 'enum' keyword to 'trait'
                if ($token->id === T_ENUM) {
                    $streamMetaData->tokenStream[$position]->text = 'trait';
                    ++$position;
                    continue;
                }
                // First T_STRING after the keyword is the enum name — rename it
                if ($token->id === T_STRING) {
                    $streamMetaData->tokenStream[$position]->text = $newClassName;
                    $classNameFound = true;
                    ++$position;
                    continue;
                }
            } else {
                // After the enum name: strip backed type (': string/int') and 'implements ...' up to '{'
                if ($token->text === '{') {
                    break;
                }
                // Keep whitespace tokens to preserve original brace placement
                if ($token->id !== T_WHITESPACE) {
                    $this->blankTokenRangePreservingNewlines($position, $position, $streamMetaData);
                }
            }

            ++$position;
        }

        // Remove all enum case declarations from the trait body.
        // Cases cannot exist in traits; they are re-declared in the proxy enum by EnumProxyGenerator.
        // The trailing whitespace token (newline + indent) after each case is intentionally kept so
        // that subsequent methods remain at their original line numbers in the woven file, which is
        // required for XDebug breakpoints to map correctly (see CLAUDE.md).
        foreach ($classNode->stmts as $stmt) {
            if (!($stmt instanceof EnumCase)) {
                continue;
            }
            $start = $stmt->getAttribute('startTokenPos');
            $end   = $stmt->getAttribute('endTokenPos');
            if (!is_int($start) || !is_int($end)) {
                continue;
            }
            // Remove the case tokens only (not the trailing whitespace/newline).
            // Keeping the trailing whitespace preserves blank lines in place of the removed case,
            // so the line numbers of all following methods are unchanged.
            for ($pos = $start; $pos <= $end; $pos++) {
                unset($streamMetaData->tokenStream[$pos]);
            }
        }

        // Strip #[\Override] from intercepted methods to prevent fatal errors on the alias.
        // PHP copies attributes to alias names (e.g. labelOriginalAlias), and since labelOriginalAlias has
        // no matching parent method, #[\Override] on the alias would be a fatal error.
        $this->stripOverrideAttributeFromInterceptedMethods($class, $advices, $methods, $streamMetaData);
    }

    /**
     * Removes #[\Override] attribute groups from all intercepted methods in the token stream.
     *
     * When a class method is aliased in the proxy trait-use block (e.g.
     * `SomeTrait::method as private methodOriginalAlias`), PHP copies the method's attributes to
     * the alias. If the original method had `#[\Override]`, the alias name has no matching
     * parent method → fatal error. We strip the attribute only from methods that will be aliased
     * (those with dynamic or static method advices).
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices
     * @param array<string, \ReflectionMethod> $methods Methods of the class by name
     */
    private function stripOverrideAttributeFromInterceptedMethods(
        ReflectionClass $class,
        array $advices,
        array $methods,
        StreamMetaData $streamMetaData,
    ): void {
        $interceptedNames = array_merge(
            array_keys($advices[AspectContainer::METHOD_PREFIX] ?? []),
            array_keys($advices[AspectContainer::STATIC_METHOD_PREFIX] ?? []),
        );

        foreach ($interceptedNames as $methodName) {
            // The declaring class, not the `class` property: a method imported from a trait reports the using
            // class there, while its attributes live in the trait source, not in this token stream
            $method = $methods[$methodName] ?? null;
            if (!$method instanceof ReflectionMethod || $method->getDeclaringClass()->name !== $class->name) {
                continue;
            }
            // Attribute names are compared as resolved by the parser, so aliases (`use Override as O; #[O]`),
            // qualified names and attributes that merely end with "Override" are told apart correctly
            $this->stripAttributes(
                $method->getNode()->attrGroups,
                static fn(Attribute $attribute): bool => self::resolveAttributeName($attribute) === 'Override',
                $streamMetaData,
            );
        }
    }

    /**
     * Removes intercepted property declarations from the woven trait body.
     *
     * The proxy class re-declares these properties with native PHP 8.4 hooks. Tokens are blanked
     * (not deleted) and every newline is kept, so original line numbers survive for debugger mapping,
     * whatever the declaration spans: multi-line defaults, attributes on their own lines or several
     * properties declared in one statement.
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices
     */
    private function removeInterceptedPropertiesFromTraitBody(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData,
    ): void {
        $interceptedProperties = array_keys($advices[AspectContainer::PROPERTY_PREFIX] ?? []);
        if ($interceptedProperties === []) {
            return;
        }
        $interceptedProperties = array_flip($interceptedProperties);

        // Plain property declarations are taken from the AST of this class/trait, so only its
        // own declarations are touched and a grouped declaration is handled once, per item
        foreach ($class->getNode()->stmts as $statement) {
            if ($statement instanceof Property) {
                $this->removeInterceptedPropertyItems($class->name, $statement, $interceptedProperties, $streamMetaData);
            }
        }
        if ($class->isTrait()) {
            return;
        }

        $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;
        $promotedAssignments = [];
        foreach ($class->getProperties($mask) as $property) {
            if (!isset($interceptedProperties[$property->getName()])) {
                continue;
            }
            if ($property->getDeclaringClass()->name !== $class->name || !method_exists($property, 'getTypeNode')) {
                continue;
            }

            $propertyNode = $property->getTypeNode();
            if ($propertyNode instanceof Param) {
                // Promoted constructor property (issue #599): the declaration cannot be removed,
                // it doubles as the constructor parameter. Demote it to a plain parameter instead
                // and assign it to the (proxy-declared) property in the constructor body.
                $this->demotePromotedPropertyParameter($propertyNode, $streamMetaData);
                $propertyName          = $property->getName();
                $promotedAssignments[] = sprintf('$this->%1$s = $%1$s;', $propertyName);
            }
        }

        if ($promotedAssignments !== []) {
            $this->injectConstructorAssignments($class, $promotedAssignments, $streamMetaData);
        }
    }

    /**
     * Demotes a promoted constructor property to a plain constructor parameter (issue #599).
     *
     * Removes only the promotion modifiers (visibility, asymmetric set-visibility, readonly,
     * final) from the parameter tokens, keeping attributes, type, name, and default value.
     * The property itself is re-declared with interception hooks in the proxy class, and the
     * value is assigned in the constructor body (see injectConstructorAssignments), which
     * routes the write through the proxy's set hook.
     *
     * Whitespace containing newlines is preserved so line numbers stay intact.
     */
    private function demotePromotedPropertyParameter(Param $parameterNode, StreamMetaData $streamMetaData): void
    {
        $start = $parameterNode->getAttribute('startTokenPos');
        $end   = $parameterNode->getAttribute('endTokenPos');
        if (!is_int($start) || !is_int($end)) {
            return;
        }

        $modifierTokenIds = [
            T_PUBLIC, T_PROTECTED, T_PRIVATE,
            T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET,
            T_READONLY, T_FINAL,
        ];

        $position = $start;
        while ($position <= $end) {
            if (!isset($streamMetaData->tokenStream[$position])) {
                ++$position;
                continue;
            }
            $token = $streamMetaData->tokenStream[$position];
            // Modifiers can only appear before the parameter variable — stop there so that
            // tokens inside the default value expression are never touched
            if ($token->id === T_VARIABLE) {
                break;
            }
            // Skip parameter attribute groups entirely: '#[' opens a bracket context that can
            // contain arbitrary nested brackets inside attribute arguments
            if ($token->id === T_ATTRIBUTE) {
                $bracketDepth = 1;
                ++$position;
                while ($position <= $end && $bracketDepth > 0) {
                    $innerText = isset($streamMetaData->tokenStream[$position]) ? $streamMetaData->tokenStream[$position]->text : '';
                    if ($innerText === '[') {
                        ++$bracketDepth;
                    } elseif ($innerText === ']') {
                        --$bracketDepth;
                    }
                    ++$position;
                }
                continue;
            }
            if (in_array($token->id, $modifierTokenIds, true)) {
                unset($streamMetaData->tokenStream[$position]);
                // Also drop the following whitespace unless it holds a newline (line budget)
                if (isset($streamMetaData->tokenStream[$position + 1])) {
                    $nextToken = $streamMetaData->tokenStream[$position + 1];
                    if ($nextToken->id === T_WHITESPACE && strpbrk($nextToken->text, "\r\n") === false) {
                        unset($streamMetaData->tokenStream[$position + 1]);
                    }
                }
            }
            ++$position;
        }
    }

    /**
     * Injects property assignments at the very beginning of the constructor body.
     *
     * The assignments are appended to the opening '{' token of the constructor body, all on
     * the same line, so the original line numbers of the constructor statements are preserved.
     *
     * @param non-empty-list<string> $assignments Assignment statements like '$this->name = $name;'
     */
    private function injectConstructorAssignments(
        ReflectionClass $class,
        array $assignments,
        StreamMetaData $streamMetaData,
    ): void {
        $constructor = $class->getConstructor();
        if ($constructor === null || !$constructor instanceof ReflectionMethod) {
            return;
        }
        $constructorNode = $constructor->getNode();
        $start = $constructorNode->getAttribute('startTokenPos');
        $end   = $constructorNode->getAttribute('endTokenPos');
        if (!is_int($start) || !is_int($end)) {
            return;
        }

        // The body '{' is the first '{' token after the parameter list closes (parenthesis
        // depth back to zero). Hook bodies of promoted parameters contain '{' too, but they
        // are always nested inside the parameter parentheses, so the depth guard skips them.
        $position          = $start;
        $seenFunction      = false;
        $seenParameterList = false;
        $parenthesisDepth  = 0;
        while ($position <= $end) {
            if (!isset($streamMetaData->tokenStream[$position])) {
                ++$position;
                continue;
            }
            $token = $streamMetaData->tokenStream[$position];
            if (!$seenFunction) {
                // Skip attribute groups before the 'function' keyword — their arguments
                // may contain arbitrary parentheses
                if ($token->id === T_ATTRIBUTE) {
                    $bracketDepth = 1;
                    ++$position;
                    while ($position <= $end && $bracketDepth > 0) {
                        $innerText = isset($streamMetaData->tokenStream[$position]) ? $streamMetaData->tokenStream[$position]->text : '';
                        if ($innerText === '[') {
                            ++$bracketDepth;
                        } elseif ($innerText === ']') {
                            --$bracketDepth;
                        }
                        ++$position;
                    }
                    continue;
                }
                $seenFunction = ($token->id === T_FUNCTION);
                ++$position;
                continue;
            }
            if ($token->text === '(') {
                ++$parenthesisDepth;
                $seenParameterList = true;
            } elseif ($token->text === ')') {
                --$parenthesisDepth;
            } elseif ($token->text === '{' && $seenParameterList && $parenthesisDepth === 0) {
                $streamMetaData->tokenStream[$position]->text .= ' ' . implode(' ', $assignments);

                return;
            }
            ++$position;
        }
    }

    /**
     * Removes the intercepted items of one property declaration statement from the trait body.
     *
     * When every item of the statement is intercepted, the whole statement (attributes, modifiers,
     * type, items and the semicolon) is blanked. Otherwise, only the intercepted items are blanked
     * together with the commas that separated them, so the remaining items keep their shared
     * attributes, modifiers and type: `public int $a = 1, $b;` with `$a` intercepted keeps
     * `public int $b;` in the trait. A newline-free marker comment is left in place of every
     * removed item.
     *
     * @param array<string, int> $interceptedProperties Intercepted property names as keys
     */
    private function removeInterceptedPropertyItems(
        string $className,
        Property $statement,
        array $interceptedProperties,
        StreamMetaData $streamMetaData,
    ): void {
        $items       = [];
        $isRemoved   = [];
        $markers     = [];
        foreach ($statement->props as $index => $item) {
            $start = $item->getAttribute('startTokenPos');
            $end   = $item->getAttribute('endTokenPos');
            if (!is_int($start) || !is_int($end)) {
                return;
            }
            $items[$index]     = [$start, $end];
            $propertyName      = $item->name->name;
            $isRemoved[$index] = isset($interceptedProperties[$propertyName]);
            if ($isRemoved[$index]) {
                $markers[$index] = sprintf('/* Moved by weaving interceptor to the {@see %s->%s} */', $className, $propertyName);
            }
        }
        if ($markers === []) {
            return;
        }

        if (count($markers) === count($items)) {
            $start = $statement->getAttribute('startTokenPos');
            $end   = $statement->getAttribute('endTokenPos');
            if (!is_int($start) || !is_int($end)) {
                return;
            }
            $this->blankTokenRangePreservingNewlines($start, $end, $streamMetaData);
            $this->prependToFirstToken($start, $end, implode(' ', $markers), $streamMetaData);

            return;
        }

        $lastIndex = array_key_last($items);
        foreach ($items as $index => [$start, $end]) {
            if ($isRemoved[$index]) {
                $this->blankTokenRangePreservingNewlines($start, $end, $streamMetaData);
                $this->prependToFirstToken($start, $end, $markers[$index], $streamMetaData);
            }
            if ($index === $lastIndex) {
                break;
            }
            // Exactly one comma separates two items; it survives only between two kept items:
            // it follows a kept item and another kept item comes later in the statement
            $keepsComma = !$isRemoved[$index] && in_array(false, array_slice($isRemoved, $index + 1), true);
            if ($keepsComma) {
                continue;
            }
            for ($position = $end + 1; $position < $items[$index + 1][0]; ++$position) {
                if (isset($streamMetaData->tokenStream[$position]) && $streamMetaData->tokenStream[$position]->text === ',') {
                    $streamMetaData->tokenStream[$position]->text = '';
                    break;
                }
            }
        }
    }

    /**
     * Prepends a text to the first token of the [$start, $end] range that is still present
     */
    private function prependToFirstToken(int $start, int $end, string $text, StreamMetaData $streamMetaData): void
    {
        for ($position = $start; $position <= $end; ++$position) {
            if (isset($streamMetaData->tokenStream[$position])) {
                $streamMetaData->tokenStream[$position]->text = $text . $streamMetaData->tokenStream[$position]->text;

                return;
            }
        }
    }

    /**
     * Performs weaving of functions in the current namespace, returns true if functions were processed, false otherwise
     *
     * @param Advisor[] $advisors List of advisors
     */
    private function processFunctions(
        array $advisors,
        StreamMetaData $metadata,
        ReflectionFileNamespace $namespace,
    ): bool {
        $wasProcessedFunctions = false;
        // A function proxy shadows the internal function with a namespaced one of the same name: that is
        // impossible in the global namespace, where it would redeclare the internal function
        if ($namespace->getName() === '') {
            return false;
        }
        $functionAdvices = $this->adviceMatcher->getAdvicesForFunctions($namespace, $advisors);
        $cacheDir        = $this->cachePathManager->getCacheDir();
        if (!empty($functionAdvices) && $cacheDir !== null) {
            $cacheDir .= self::FUNCTIONS_CACHE_SUFFIX;
            $fileName = str_replace('\\', '/', $namespace->getName()) . '.php';

            $functionFileName = $cacheDir . $fileName;
            $filemtime = file_exists($functionFileName) ? filemtime($functionFileName) : false;
            if ($filemtime === false || !$this->container->isFreshSince($filemtime)) {
                $functionAdvices = AbstractJoinpoint::flatAndSortAdvices($functionAdvices);
                $generator       = new FunctionProxyGenerator($namespace, $functionAdvices);
                $this->cachePathManager->getCacheFileWriter()->write($functionFileName, $generator->generate());
            }
            $content = 'include_once AOP_CACHE_DIR . ' . var_export(self::FUNCTIONS_CACHE_SUFFIX . $fileName, true) . ';';

            $lastTokenPosition = $namespace->getLastTokenPosition();
            $metadata->tokenStream[$lastTokenPosition]->text .= PHP_EOL . $content;
            $wasProcessedFunctions = true;
        }

        return $wasProcessedFunctions;
    }

    /**
     * Save AOP proxy to the separate file anr returns the php source code for inclusion
     */
    private function saveProxyToCache(ReflectionClass $class, string $childCode): string
    {
        $cacheRootDir = $this->cachePathManager->getCacheDir();
        if ($cacheRootDir === null) {
            return '';
        }
        $classFileName = $class->getFileName();
        if ($classFileName === false) {
            return '';
        }
        // Classes outside the application root keep their absolute path below the cache root
        $relativePath      = ltrim(PathResolver::rebase($classFileName, $this->options['appDir'], '') ?? $classFileName, '/\\');
        $proxyRelativePath = str_replace('\\', '/', $relativePath);
        $proxyFileName     = $cacheRootDir . '/' . $proxyRelativePath;

        // Atomic write: a concurrent request including the proxy never sees a partial file
        $this->cachePathManager->getCacheFileWriter()->write($proxyFileName, '<?php' . PHP_EOL . $childCode);

        return 'include_once AOP_CACHE_DIR . ' . var_export('/' . $proxyRelativePath, true) . ';';
    }

    /**
     * Utility method to load and register unloaded aspects
     *
     * @param Aspect[] $unloadedAspects List of unloaded aspects
     */
    private function loadAndRegisterAspects(array $unloadedAspects): void
    {
        foreach ($unloadedAspects as $unloadedAspect) {
            $this->aspectLoader->loadAndRegister($unloadedAspect);
        }
    }
}
