<?php

declare(strict_types = 1);
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
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\AdviceMatcher;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoaderInterface;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionFile;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\ParserReflection\ReflectionMethod;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\EnumProxyGenerator;
use Go\Proxy\FunctionProxyGenerator;
use Go\Proxy\TraitProxyGenerator;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use ReflectionProperty;

/**
 * Main transformer that performs weaving of aspects into the source code
 */
class WeavingTransformer extends BaseSourceTransformer
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
        protected readonly AspectLoaderInterface $aspectLoader
    ) {
        parent::__construct($kernel);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     */
    public function transform(StreamMetaData $metadata): TransformerResultEnum
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

                // Skip interfaces and aspects — enums are now supported via EnumProxyGenerator
                if ($class->isInterface() || in_array(Aspect::class, $class->getInterfaceNames(), true)) {
                    continue;
                }
                $wasClassProcessed = $this->processSingleClass(
                    $advisors,
                    $metadata,
                    $class,
                    $parsedSource->isStrictMode()
                );
                $totalTransformations += (int) $wasClassProcessed;
            }
            $wasFunctionsProcessed = $this->processFunctions($advisors, $metadata, $namespace);
            $totalTransformations += (int) $wasFunctionsProcessed;
        }

        $result = ($totalTransformations > 0) ? TransformerResultEnum::RESULT_TRANSFORMED : TransformerResultEnum::RESULT_ABSTAIN;

        return $result;
    }

    /**
     * Performs weaving of single class if needed, returns true if the class was processed
     *
     * @param Advisor[]       $advisors List of advisors
     * @param StreamMetaData  $metadata
     * @param ReflectionClass $class
     * @param bool            $useStrictMode If the source file used strict mode, the proxy should too
     * @return bool
     */
    private function processSingleClass(
        array $advisors,
        StreamMetaData $metadata,
        ReflectionClass $class,
        bool $useStrictMode
    ): bool {
        $advices = $this->adviceMatcher->getAdvicesForClass($class, $advisors);

        if (empty($advices)) {
            // Fast return if there aren't any advices for that class
            return false;
        }

        // Sort advices in advance to keep the correct order in cache, and leave only keys for the cache
        $advices = AbstractJoinpoint::flatAndSortAdvices($advices);

        // Prepare new class name
        $newClassName = $class->getShortName() . AspectContainer::AOP_PROXIED_SUFFIX;
        $newFqcn      = ($class->getNamespaceName() !== '' ? $class->getNamespaceName() . '\\' : '') . $newClassName;

        // For traits: rename the trait (legacy approach, TraitProxyGenerator generates a child trait).
        // For enums: convert the enum body to a trait (cases extracted to proxy enum by EnumProxyGenerator).
        // For classes: convert the class body to a trait (new trait-based engine).
        if ($class->isTrait()) {
            $this->commentOutInterceptedPropertiesInTraitBody($class, $advices, $metadata);
            $this->adjustOriginalTrait($class, $metadata, $newClassName);
            $childProxyGenerator = new TraitProxyGenerator($class, $newFqcn, $advices);
        } elseif ($class->isEnum()) {
            $this->convertEnumToTrait($class, $advices, $metadata, $newClassName);
            $childProxyGenerator = new EnumProxyGenerator($class, $newFqcn, $advices);
        } else {
            $this->convertClassToTrait($class, $advices, $metadata, $newClassName);
            $childProxyGenerator = new ClassProxyGenerator($class, $newFqcn, $advices);
        }

        $classFileName = $class->getFileName();
        if ($classFileName === false) {
            return false;
        }
        $refNamespace = new ReflectionFileNamespace($classFileName, $class->getNamespaceName());
        foreach ($refNamespace->getNamespaceAliases() as $fqdn => $alias) {
            $childProxyGenerator->addUse($fqdn, $alias);
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
     * Adjust definition of original trait source to enable extending
     */
    private function adjustOriginalTrait(
        ReflectionClass $class,
        StreamMetaData $streamMetaData,
        string $newClassName
    ): void {
        $classNode = $class->getNode();
        $position = $this->getPositionAfterAttributeGroups($classNode);
        if (!is_int($position)) {
            return;
        }
        do {
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
        } while (true);
    }

    /**
     * Returns the token position where the class/enum declaration scan should start.
     *
     * A ClassLike node's startTokenPos includes its attribute groups (`#[...]`), so scanning
     * from there would rename the first T_STRING inside the attribute to the trait name and
     * then delete the real class header (see https://github.com/goaop/framework/issues/598).
     * Class-level attributes are kept as-is on the generated trait — attributes are legal
     * on traits — so the scan starts right after the last attribute group.
     */
    private function getPositionAfterAttributeGroups(ClassLike $classNode): ?int
    {
        $position = $classNode->getAttribute('startTokenPos');
        if (!is_int($position)) {
            return null;
        }
        $lastAttrGroup = end($classNode->attrGroups);
        if ($lastAttrGroup !== false) {
            $attrGroupsEnd = $lastAttrGroup->getAttribute('endTokenPos');
            if (is_int($attrGroupsEnd)) {
                $position = $attrGroupsEnd + 1;
            }
        }

        return $position;
    }

    /**
     * Convert a regular class declaration into a trait for the trait-based AOP engine.
     *
     * Performs the following token-stream modifications in-place:
     *  - Removes 'final' and 'abstract' modifiers from the class keyword
     *  - Changes 'class' keyword text to 'trait'
     *  - Renames the class to $newClassName (__AopProxied suffix)
     *  - Removes the 'extends X' and 'implements Y, Z' clauses (moved to the proxy class)
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices List of class advices
     */
    private function convertClassToTrait(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData,
        string $newClassName
    ): void {
        $classNode = $class->getNode();
        $position = $this->getPositionAfterAttributeGroups($classNode);
        if (!is_int($position)) {
            return;
        }

        $classNameFound = false;

        do {
            if (!isset($streamMetaData->tokenStream[$position])) {
                ++$position;
                continue;
            }

            $token = $streamMetaData->tokenStream[$position];

            if (!$classNameFound) {
                // Remove 'final' modifier (and trailing whitespace) — traits cannot be final
                if ($token->id === T_FINAL) {
                    unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position + 1]);
                    ++$position;
                    continue;
                }
                // Remove 'abstract' modifier (and trailing whitespace) — trait keyword itself has no modifier
                if ($token->id === T_ABSTRACT) {
                    unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position + 1]);
                    ++$position;
                    continue;
                }
                // Remove 'readonly' modifier — traits cannot be readonly
                if ($token->id === T_READONLY) {
                    unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position + 1]);
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
                    unset($streamMetaData->tokenStream[$position]);
                }
            }

            ++$position;
        } while (true);

        // Strip #[\Override] from intercepted methods.
        // PHP copies attributes to alias names (e.g. __aop__foo). Since __aop__foo has no parent
        // match, PHP would raise a fatal error if #[\Override] were present on the alias.
        $this->commentOutInterceptedPropertiesInTraitBody($class, $advices, $streamMetaData);
        $this->stripOverrideAttributeFromInterceptedMethods($class, $advices, $streamMetaData);
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
        foreach ($classNode->attrGroups as $attrGroup) {
            $incompatibleAttributes = [];
            foreach ($attrGroup->attrs as $attribute) {
                // Names are resolved by parser-reflection's NameResolver, so global attribute
                // classes are FullyQualified nodes ('Attribute', 'AllowDynamicProperties').
                if (in_array(ltrim($attribute->name->toString(), '\\'), self::TRAIT_INCOMPATIBLE_ATTRIBUTES, true)) {
                    $incompatibleAttributes[] = $attribute;
                }
            }
            if ($incompatibleAttributes === []) {
                continue;
            }
            if (count($incompatibleAttributes) === count($attrGroup->attrs)) {
                // Every attribute in the group is incompatible — blank out the whole group '#[...]'
                $start = $attrGroup->getAttribute('startTokenPos');
                $end   = $attrGroup->getAttribute('endTokenPos');
                if (is_int($start) && is_int($end)) {
                    $this->blankTokenRangePreservingNewlines($start, $end, $streamMetaData);
                }
                continue;
            }
            foreach ($incompatibleAttributes as $attribute) {
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
     *  - Renames the enum to $newClassName (__AopProxied suffix)
     *  - Removes the backed type (': string' / ': int') and any 'implements ...' clause
     *  - Removes all enum case declarations from the body (cases live in the proxy enum instead)
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices List of class advices
     */
    private function convertEnumToTrait(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData,
        string $newClassName
    ): void {
        $classNode = $class->getNode();
        $position = $this->getPositionAfterAttributeGroups($classNode);
        if (!is_int($position)) {
            return;
        }

        $classNameFound = false;

        do {
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
                    unset($streamMetaData->tokenStream[$position]);
                }
            }

            ++$position;
        } while (true);

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
        // PHP copies attributes to alias names (e.g. __aop__label), and since __aop__label has
        // no matching parent method, #[\Override] on the alias would be a fatal error.
        $this->stripOverrideAttributeFromInterceptedMethods($class, $advices, $streamMetaData);
    }

    /**
     * Removes #[\Override] attribute groups from all intercepted methods in the token stream.
     *
     * When a class method is aliased in the proxy trait-use block (e.g.
     * `SomeTrait::method as private __aop__method`), PHP copies the method's attributes to
     * the alias. If the original method had `#[\Override]`, the alias name has no matching
     * parent method → fatal error. We strip the attribute only from methods that will be aliased
     * (those with dynamic or static method advices).
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices
     */
    private function stripOverrideAttributeFromInterceptedMethods(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData
    ): void {
        $interceptedNames = array_merge(
            array_keys($advices[AspectContainer::METHOD_PREFIX] ?? []),
            array_keys($advices[AspectContainer::STATIC_METHOD_PREFIX] ?? [])
        );

        foreach ($interceptedNames as $methodName) {
            if (!$class->hasMethod($methodName)) {
                continue;
            }
            /** @var ReflectionMethod $method */
            $method = $class->getMethod($methodName);
            if ($method->getDeclaringClass()->name !== $class->name) {
                continue;
            }
            $methodNode = $method->getNode();
            $start = $methodNode->getAttribute('startTokenPos');
            $end   = $methodNode->getAttribute('endTokenPos');
            if (!is_int($start) || !is_int($end)) {
                continue;
            }

            // Scan from method start for #[\Override] attribute groups, stopping at
            // the first modifier keyword or 'function'.
            $pos = $start;
            while ($pos <= $end) {
                if (!isset($streamMetaData->tokenStream[$pos])) {
                    $pos++;
                    continue;
                }
                $tok = $streamMetaData->tokenStream[$pos];
                // Stop at any method modifier or 'function' keyword
                if (in_array($tok->id, [T_FUNCTION, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY], true)) {
                    break;
                }
                if ($tok->id !== T_ATTRIBUTE) {
                    $pos++;
                    continue;
                }
                // Scan from '#[' to the closing ']', tracking:
                //   $topLevelCommas  — positions of ',' at argument depth 0 (not inside nested parens)
                //   $overridePos     — position of the Override token at depth 0, if present
                $groupPositions = [$pos];
                $topLevelCommas = [];
                $overridePos    = null;
                $depth          = 0;
                $scanPos        = $pos + 1;
                while ($scanPos <= $end) {
                    if (!isset($streamMetaData->tokenStream[$scanPos])) {
                        $scanPos++;
                        continue;
                    }
                    $t = $streamMetaData->tokenStream[$scanPos];
                    $groupPositions[] = $scanPos;
                    if ($t->text === '(') {
                        $depth++;
                    } elseif ($t->text === ')') {
                        $depth--;
                    } elseif ($t->text === ',' && $depth === 0) {
                        $topLevelCommas[] = $scanPos;
                    }
                    // Match Override only at depth 0 so that SomeAttr(name: 'Override') is not affected
                    // #[Override]  → T_STRING 'Override'
                    // #[\Override] → T_NAME_FULLY_QUALIFIED '\Override'
                    if ($depth === 0 && (
                        ($t->id === T_STRING && $t->text === 'Override')
                        || ($t->id === T_NAME_FULLY_QUALIFIED && str_ends_with($t->text, 'Override'))
                    )) {
                        $overridePos = $scanPos;
                    }
                    if ($t->text === ']') {
                        break;
                    }
                    $scanPos++;
                }
                /** @var int $groupEnd */
                $groupEnd = end($groupPositions);
                if ($overridePos === null) {
                    // No Override attribute in this group — advance past it
                    $pos = $groupEnd + 1;
                } elseif (empty($topLevelCommas)) {
                    // #[Override] is the only attribute — remove the entire group + trailing whitespace
                    foreach ($groupPositions as $gPos) {
                        unset($streamMetaData->tokenStream[$gPos]);
                    }
                    $afterPos = $groupEnd + 1;
                    if (isset($streamMetaData->tokenStream[$afterPos])
                        && $streamMetaData->tokenStream[$afterPos]->id === T_WHITESPACE) {
                        unset($streamMetaData->tokenStream[$afterPos]);
                    }
                    $pos = $afterPos + 1;
                } else {
                    // Multi-attribute group: remove only the Override token and one adjacent comma+whitespace,
                    // keeping '#[' and the remaining attribute entries intact.
                    unset($streamMetaData->tokenStream[$overridePos]);
                    // Prefer removing a trailing comma (first ',' after Override),
                    // fall back to the leading comma (last ',' before Override).
                    $commaToRemove = null;
                    foreach ($topLevelCommas as $commaPos) {
                        if ($commaPos > $overridePos) {
                            $commaToRemove = $commaPos;
                            break;
                        }
                    }
                    if ($commaToRemove === null) {
                        foreach (array_reverse($topLevelCommas) as $commaPos) {
                            if ($commaPos < $overridePos) {
                                $commaToRemove = $commaPos;
                                break;
                            }
                        }
                    }
                    if ($commaToRemove !== null) {
                        unset($streamMetaData->tokenStream[$commaToRemove]);
                        $nextPos = $commaToRemove + 1;
                        if (isset($streamMetaData->tokenStream[$nextPos])
                            && $streamMetaData->tokenStream[$nextPos]->id === T_WHITESPACE) {
                            unset($streamMetaData->tokenStream[$nextPos]);
                        }
                    }
                    $pos = $groupEnd + 1;
                }
            }
        }
    }

    /**
     * Removes intercepted property declarations from the woven trait body.
     *
     * The proxy class re-declares these properties with native PHP 8.4 hooks. Tokens are neutralised
     * (not deleted) to preserve original line numbers for debugger mapping.
     *
     * @param array<string, array<string, list<string|\Go\Aop\Framework\GeneratedInterceptor>>> $advices
     */
    private function commentOutInterceptedPropertiesInTraitBody(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData
    ): void {
        $interceptedProperties = array_keys($advices[AspectContainer::PROPERTY_PREFIX] ?? []);
        if ($interceptedProperties === []) {
            return;
        }
        $interceptedProperties = array_flip($interceptedProperties);

        if ($class->isTrait()) {
            $classNode = $class->getNode();
            foreach ($classNode->stmts as $statement) {
                if (!$statement instanceof Property) {
                    continue;
                }
                $statementContainsInterceptedProperty = false;
                foreach ($statement->props as $propertyNode) {
                    if (isset($interceptedProperties[$propertyNode->name->name])) {
                        $statementContainsInterceptedProperty = true;
                        break;
                    }
                }
                if (!$statementContainsInterceptedProperty) {
                    continue;
                }
                $start = $statement->getAttribute('startTokenPos');
                $end = $statement->getAttribute('endTokenPos');
                if (!is_int($start) || !is_int($end)) {
                    continue;
                }
                $this->commentOutMovedPropertyTokenRange($class->name, $statement->props[0]->name->name, $start, $end, $streamMetaData);
            }

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
            if (!is_object($propertyNode) || !method_exists($propertyNode, 'getAttribute')) {
                continue;
            }
            if ($propertyNode instanceof Param) {
                // Promoted constructor property (issue #599): the declaration cannot be commented
                // out — it doubles as the constructor parameter. Demote it to a plain parameter
                // instead and assign it to the (proxy-declared) property in the constructor body.
                $this->demotePromotedPropertyParameter($propertyNode, $streamMetaData);
                $propertyName          = $property->getName();
                $promotedAssignments[] = sprintf('$this->%1$s = $%1$s;', $propertyName);
                continue;
            }
            $start = $propertyNode->getAttribute('startTokenPos');
            $end   = $propertyNode->getAttribute('endTokenPos');
            if (!is_int($start) || !is_int($end)) {
                continue;
            }
            $this->commentOutMovedPropertyTokenRange($class->name, $property->getName(), $start, $end, $streamMetaData);
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
        StreamMetaData $streamMetaData
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

    private function commentOutMovedPropertyTokenRange(
        string $className,
        string $propertyName,
        int $start,
        int $end,
        StreamMetaData $streamMetaData
    ): void {
        $firstTokenPosition = $start;
        while ($firstTokenPosition <= $end && !isset($streamMetaData->tokenStream[$firstTokenPosition])) {
            ++$firstTokenPosition;
        }
        $lastTokenPosition = $end;
        while ($lastTokenPosition >= $start && !isset($streamMetaData->tokenStream[$lastTokenPosition])) {
            --$lastTokenPosition;
        }
        if (!isset($streamMetaData->tokenStream[$firstTokenPosition], $streamMetaData->tokenStream[$lastTokenPosition])) {
            return;
        }

        $streamMetaData->tokenStream[$firstTokenPosition]->text = '// ' . $streamMetaData->tokenStream[$firstTokenPosition]->text;

        $suffix = sprintf(
            ' // Moved by weaving interceptor to the {@see %s->%s}',
            $className,
            $propertyName
        );
        $lastTokenText = $streamMetaData->tokenStream[$lastTokenPosition]->text;
        $newLine = $this->findLastNewlinePosition($lastTokenText);
        if ($newLine !== null) {
            $streamMetaData->tokenStream[$lastTokenPosition]->text = substr($lastTokenText, 0, $newLine['position'])
                . $suffix
                . substr($lastTokenText, $newLine['position'], $newLine['length'])
                . substr($lastTokenText, $newLine['position'] + $newLine['length']);
        } else {
            $streamMetaData->tokenStream[$lastTokenPosition]->text .= $suffix;
        }
    }

    /**
     * @return array{position: int, length: int}|null
     */
    private function findLastNewlinePosition(string $text): ?array
    {
        $position = strrpos($text, "\r\n");
        if ($position !== false) {
            return ['position' => $position, 'length' => 2];
        }
        $position = strrpos($text, "\n");
        if ($position !== false) {
            return ['position' => $position, 'length' => 1];
        }
        $position = strrpos($text, "\r");
        if ($position !== false) {
            return ['position' => $position, 'length' => 1];
        }

        return null;
    }

    /**
     * Performs weaving of functions in the current namespace, returns true if functions were processed, false otherwise
     *
     * @param Advisor[] $advisors List of advisors
     */
    private function processFunctions(
        array $advisors,
        StreamMetaData $metadata,
        ReflectionFileNamespace $namespace
    ): bool {
        $wasProcessedFunctions = false;
        $functionAdvices = $this->adviceMatcher->getAdvicesForFunctions($namespace, $advisors);
        $cacheDir        = $this->cachePathManager->getCacheDir();
        if (!empty($functionAdvices) && $cacheDir !== null) {
            $cacheDir .= self::FUNCTIONS_CACHE_SUFFIX;
            $fileName = str_replace('\\', '/', $namespace->getName()) . '.php';

            $functionFileName = $cacheDir . $fileName;
            $filemtime = file_exists($functionFileName) ? filemtime($functionFileName) : false;
            if ($filemtime === false || !$this->container->isFreshSince($filemtime)) {
                $functionAdvices = AbstractJoinpoint::flatAndSortAdvices($functionAdvices);
                $dirname         = dirname($functionFileName);
                if (!file_exists($dirname)) {
                    mkdir($dirname, $this->options['cacheFileMode'], true);
                }
                $generator = new FunctionProxyGenerator($namespace, $functionAdvices);
                file_put_contents($functionFileName, $generator->generate(), LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($functionFileName, $this->options['cacheFileMode'] & (~0111));
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
        $relativePath      = str_replace($this->options['appDir'] . DIRECTORY_SEPARATOR, '', $classFileName);
        $proxyRelativePath = str_replace('\\', '/', $relativePath);
        $proxyFileName     = $cacheRootDir . '/' . $proxyRelativePath;
        $dirname           = dirname($proxyFileName);
        if (!file_exists($dirname)) {
            mkdir($dirname, $this->options['cacheFileMode'], true);
        }

        $body = '<?php' . PHP_EOL . $childCode;

        // PHP core refuses the LOCK_EX flag for any non-"file://" stream wrapper path,
        // so it can not be used when the cache is placed on a virtual filesystem
        $isStreamPath = str_contains($proxyFileName, '://');
        file_put_contents($proxyFileName, $body, $isStreamPath ? 0 : LOCK_EX);
        // For cache files we don't want executable bits by default
        chmod($proxyFileName, $this->options['cacheFileMode'] & (~0111));

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
