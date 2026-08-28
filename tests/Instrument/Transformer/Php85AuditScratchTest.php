<?php

declare(strict_types = 1);
/*
 * PHP 8.5 AUDIT HARNESS (temporary, lives only on the audit branch).
 * Weaves PHP 8.1-8.5 feature fixtures from _files/audit through the real
 * WeavingTransformer and validates that both the woven trait and the
 * generated proxy still lint and preserve the feature under test.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Advisor;
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
    private const FIXTURE_DIR = __DIR__ . '/../../Stubs/Audit';

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
                'excludePaths'  => []
            ],
            $container
        );
        $this->cachePathManager = new CachePathManager($this->kernel);

        $this->transformer = new WeavingTransformer(
            $this->kernel,
            $this->getInterceptEverythingMatcher(),
            $this->cachePathManager,
            $loader
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureNames(): array
    {
        $names = [];
        foreach (glob(self::FIXTURE_DIR . '/*.php') as $file) {
            $name = basename($file, '.php');
            $names[$name] = [$name];
        }

        return $names;
    }

    /**
     * Fixtures currently known to produce a broken weave, keyed to their tracking issue.
     * A fix PR that resolves one of these MUST remove the entry (the test then asserts success).
     */
    private const KNOWN_GAPS = [
        // #598/#599/#601/#602/#603 are fixed on master; #600 lands with PR #614
        'Php81EnumConstExprCases' => 'https://github.com/goaop/framework/issues/600',
        // Follow-ups found after the #598/#599 fixes landed:
        // #[\Attribute] on a trait only became a compile error in PHP 8.5,
        // so these three are gaps on 8.5+ but weave cleanly on 8.4
        'ConstAttr'               => 'https://github.com/goaop/framework/issues/615',
        'ExprAttr'                => 'https://github.com/goaop/framework/issues/615',
        'RichAttr'                => 'https://github.com/goaop/framework/issues/615',
        // new-in-initializer default copied onto the proxy hook property
        'Php81NewInInitializers'  => 'https://github.com/goaop/framework/issues/616',
    ];

    /** Fixtures whose KNOWN_GAPS entry applies only on PHP >= 8.5 (see above). */
    private const GAP_ONLY_ON_85 = ['ConstAttr' => true, 'ExprAttr' => true, 'RichAttr' => true];

    #[DataProvider('fixtureNames')]
    public function testWeaveAndLint(string $name): void
    {
        // 8.5-only syntax cannot lint (nor natively reflect) on older runtimes
        if (str_starts_with($name, 'Php85') && PHP_VERSION_ID < 80500) {
            $this->markTestSkipped('Fixture uses PHP 8.5 syntax');
        }

        $problems = $this->weaveAndCollectProblems($name);

        $isKnownGap = isset(self::KNOWN_GAPS[$name])
            && (!isset(self::GAP_ONLY_ON_85[$name]) || PHP_VERSION_ID >= 80500);

        if ($isKnownGap) {
            $issue = self::KNOWN_GAPS[$name];
            $this->assertNotSame(
                [],
                $problems,
                "$name weaves cleanly now — the gap tracked in $issue looks fixed. " .
                'Remove it from KNOWN_GAPS so this stays asserted.'
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
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = true;
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
                    $advices[AspectContainer::PROPERTY_PREFIX][$property->name][$advisorId] = true;
                }
                return $advices;
            });
        $mock->method('getAdvicesForFunctions')->willReturn([]);

        return $mock;
    }

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
        $source   = file_get_contents($fileName);
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
                [Advisor::class, []]
            ]);

        return $container;
    }
}
