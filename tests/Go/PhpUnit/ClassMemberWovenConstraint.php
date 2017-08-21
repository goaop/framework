<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\PhpUnit;

use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;
use PHPUnit_Framework_Constraint as Constraint;

/**
 * Asserts that class member is woven for given class.
 */
class ClassMemberWovenConstraint extends Constraint
{
    /**
     * @var array
     */
    private $configuration;

    public function __construct(array $configuration)
    {
        parent::__construct();
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($other)
    {
        if (!$other instanceof ClassAdvisorIdentifier) {
            throw new \InvalidArgumentException(sprintf('Expected instance of "%s", got "%s".', ClassAdvisorIdentifier::class, is_object($other) ? get_class($other) : gettype($other)));
        }

        $wovenAdvisorIdentifiers = $this->getWovenAdvisorIdentifiers($other->getClass());

        $target = $other->getTarget();

        if (!isset($wovenAdvisorIdentifiers[$target])) {
            return false;
        }

        if (!isset($wovenAdvisorIdentifiers[$target][$other->getSubject()])) {
            return false;
        }

        if (null === $other->getAdvisorIdentifier()) {
            return true; // if advisor identifier is not specified, that means that any matches, so weaving exists.
        }

        $index        = $other->getIndex();
        $advisorIndex = array_search($other->getAdvisorIdentifier(), $wovenAdvisorIdentifiers[$target][$other->getSubject()], true);
        $isIndexValid = ($index === null) || ($advisorIndex === $index);

        return $advisorIndex !== false && $isIndexValid;
    }

    /**
     * {@inheritdoc}
     */
    public function toString()
    {
        return 'join point exists.';
    }

    /**
     * Get woven advisor identifiers.
     *
     * @param string $className
     *
     * @return array
     */
    private function getWovenAdvisorIdentifiers($className)
    {
        $parsedReflectionClass = new ReflectionClass($className);
        $originalClassFile     = $parsedReflectionClass->getFileName();
        $originalNamespace     = $parsedReflectionClass->getNamespaceName();

        $fileRelativePath = substr($originalClassFile, strlen(PathResolver::realpath($this->configuration['appDir'])));
        $proxyFileName    = $this->configuration['cacheDir'] . '/_proxies' . $fileRelativePath;
        $proxyFileContent = file_get_contents($proxyFileName);

        // To prevent deep analysis of parents, we just cut everything after "extends"
        $proxyFileContent = preg_replace('/extends.*/', '', $proxyFileContent);
        $proxyFileAST     = ReflectionEngine::parseFile($proxyFileName, $proxyFileContent);

        $proxyReflectionFile  = new ReflectionFile($proxyFileName, $proxyFileAST);
        $proxyClassReflection = $proxyReflectionFile->getFileNamespace($originalNamespace)->getClass($className);

        $advisorIdentifiers = $proxyClassReflection->getStaticPropertyValue('__joinPoints');

        return $advisorIdentifiers;
    }
}
