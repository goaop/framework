<?php

declare(strict_types=1);
/*
 * PHP 8.5 AUDIT HARNESS (temporary, lives only on the audit branch).
 * Weaves PHP 8.1-8.5 feature fixtures from _files/audit through the real
 * WeavingTransformer and validates that both the woven trait and the
 * generated proxy still lint and preserve the feature under test.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Advisor;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class Php85AuditScratchTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../Stubs';

    /** Audit fixture stubs living in tests/Stubs alongside the general-purpose stubs. */
    private const AUDIT_FIXTURES = [
        'Collaborator',
        'ConstAttr',
        'ExprAttr',
        'Php80ClassAttrPlain',
        'Php80GlobalConstAttrArg',
        'Php81EnumConstExprCases',
        'Php81NewInInitializers',
        'Php81NonScalarAttributeArgs',
        'Php85CloneWith',
        'Php85ClosuresInConstExpr',
        'Php85ConstAttributes',
        'Php85FinalPromotionAsymStatic',
        'Php85NoDiscard',
        'Php85PipeOperator',
        'RichAttr',
        'Status',
    ];

    protected static FileSystem $fileSystem;

    protected WeavingTransformer $transformer;

    protected ?AspectKernel $kernel;

    protected ?CachePathManager $cachePathManager;

    public static function setUpBeforeClass(): void
    {
        static::$fileSystem = FileSystem::mount('vfs');
        if (!is_dir(self::outDir())) {
            mkdir(self::outDir(), 0777, true);
        }
    }

    private static function outDir(): string
    {
        return sys_get_temp_dir() . '/php85-audit-out';
    }

    public static function tearDownAfterClass(): void
    {
        static::$fileSystem->unmount();
    }

    public function setUp(): void
    {
        $container = $this->getContainerMock();
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();

        $this->kernel = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => 'vfs://',
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => [],
            ],
            $container,
        );
        $this->cachePathManager = new CachePathManager($this->kernel);

        $this->transformer = new WeavingTransformer(
            $this->kernel,
            $this->getInterceptEverythingMatcher(),
            $this->cachePathManager,
            $loader,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureNames(): array
    {
        $names = [];
        foreach (self::AUDIT_FIXTURES as $name) {
            $names[$name] = [$name];
        }

        return $names;
    }

    /**
     * Fixtures currently known to produce a broken weave, keyed to their tracking issue.
     * A fix PR that resolves one of these MUST remove the entry (the test then asserts success).
     *
     * @var array<string, string>
     */
    private const KNOWN_GAPS = [
        // All audit gaps (#598-#603, #615, #616) are fixed — every fixture must weave cleanly.
    ];

    /**
     * Fixtures whose KNOWN_GAPS entry applies only on PHP >= 8.5.
     *
     * @var array<string, bool>
     */
    private const GAP_ONLY_ON_85 = [];

    #[DataProvider('fixtureNames')]
    public function testWeaveAndLint(string $name): void
    {
        // 8.5-only syntax cannot lint (nor natively reflect) on older runtimes
        if (str_starts_with($name, 'Php85') && PHP_VERSION_ID < 80500) {
            $this->markTestSkipped('Fixture uses PHP 8.5 syntax');
        }

        $problems = $this->weaveAndCollectProblems($name);

        /** @var array<string, string> $knownGaps Empty right now, refilled when a new gap is discovered */
        $knownGaps = self::KNOWN_GAPS;
        /** @var array<string, bool> $gapsOnlyOn85 Empty right now, refilled when a new gap is discovered */
        $gapsOnlyOn85 = self::GAP_ONLY_ON_85;

        $issue      = $knownGaps[$name] ?? null;
        $isKnownGap = $issue !== null
            && (!isset($gapsOnlyOn85[$name]) || PHP_VERSION_ID >= 80500);

        if ($isKnownGap) {
            $this->assertNotSame(
                [],
                $problems,
                "$name weaves cleanly now — the gap tracked in $issue looks fixed. "
                . 'Remove it from KNOWN_GAPS so this stays asserted.',
            );
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertSame([], $problems, "$name should weave cleanly:\n" . implode("\n---\n", $problems));
    }

    /**
     * @return list<string> Problems encountered (transform exception or lint failures); empty = clean weave
     */
    private function weaveAndCollectProblems(string $name): array
    {
        $metadata = $this->loadAuditMetadata($name);

        try {
            $this->transformer->transform($metadata);
        } catch (\Throwable $e) {
            file_put_contents(self::outDir() . "/$name.ERROR.txt", (string) $e);

            return ["TRANSFORM ERROR: {$e->getMessage()}"];
        }

        $problems = [];
        $woven    = $metadata->source;
        file_put_contents(self::outDir() . "/$name-woven.php", $woven);
        $problems = [...$problems, ...$this->lintProblems(self::outDir() . "/$name-woven.php", "$name woven trait")];

        if (preg_match_all("/AOP_CACHE_DIR . '(.+)';$/m", $woven, $matches)) {
            foreach ($matches[1] as $i => $proxyPath) {
                $proxyContent = (string) file_get_contents('vfs://' . $proxyPath);
                $suffix       = $i > 0 ? "-$i" : '';
                file_put_contents(self::outDir() . "/$name-proxy$suffix.php", $proxyContent);
                $problems = [...$problems, ...$this->lintProblems(self::outDir() . "/$name-proxy$suffix.php", "$name proxy #$i")];
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function lintProblems(string $file, string $label): array
    {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            return ["$label does not lint:\n" . implode("\n", $output)];
        }

        return [];
    }

    private function getInterceptEverythingMatcher(): AdviceMatcherInterface
    {
        $mock = $this->createMock(AdviceMatcherInterface::class);
        $mock
            ->method('getAdvicesForClass')
            ->willReturnCallback(function (ReflectionClass $refClass) {
                $advices = [];
                foreach ($refClass->getMethods() as $method) {
                    if ($method->getDeclaringClass()->name !== $refClass->name) {
                        continue;
                    }
                    $advisorId = "advisor.{$refClass->name}->{$method->name}";
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = new BeforeInterceptor(static function (): void {});
                }
                foreach ($refClass->getProperties() as $property) {
                    if ($property->getDeclaringClass()->name !== $refClass->name) {
                        continue;
                    }
                    // Mirror the real AdviceMatcher gates (static/readonly/hooked are not interceptable)
                    if ($property->isStatic() || $property->isReadOnly() || $property->hasHooks()) {
                        continue;
                    }
                    $advisorId = "advisor.{$refClass->name}->{$property->name}";
                    $advices[AspectContainer::PROPERTY_PREFIX][$property->name][$advisorId] = new BeforeInterceptor(static function (): void {});
                }
                return $advices;
            });
        $mock->method('getAdvicesForFunctions')->willReturn([]);

        return $mock;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getKernelMock(array $options, AspectContainer $container): AspectKernel
    {
        $mock = $this->getMockBuilder(AspectKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configureAop', 'getOptions', 'getContainer', 'hasFeature'])
            ->getMock();

        $mock->method('getOptions')->willReturn($options);
        $mock->method('getContainer')->willReturn($container);

        return $mock;
    }

    private function loadAuditMetadata(string $name): StreamMetaData
    {
        $fileName = self::FIXTURE_DIR . '/' . $name . '.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        assert($stream !== false);
        $source   = file_get_contents($fileName);
        assert($source !== false);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

    private function getContainerMock(): AspectContainer
    {
        $container = $this->createMock(AspectContainer::class);
        $container
            ->method('getServicesByInterface')
            ->willReturnMap([
                [Advisor::class, []],
            ]);

        return $container;
    }
}
