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

namespace Go\Performance;

use Closure;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\SyntaxTreeRewriter;
use Go\Instrument\Transformer\WeavingTransformer;
use Go\VirtualFileSystem\FileSystem;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Go\Aop\WeavingDriver;

/**
 * Performance of the source transformation, which runs on every cache miss of a loaded file
 *
 * Each case times `transform()` over a fixed corpus and compares the median time with a plain walk of the
 * nikic/php-parser syntax trees by a no-op visitor, measured in the same process right before. The ratio is checked
 * against a budget, so the result does not depend on the machine. The rewriter cases transform the same php-parser
 * trees; the weaving case transforms the small weaving fixtures, a plain walk of which is too short to calibrate. Parsing is done before timing, the token streams are restored between
 * the rounds. Excluded from the default run: `composer test:performance:transformation` (JIT disabled). Run it
 * before and after every change of a SourceTransformer, a NodeRewriter or their wiring and keep the ratios from
 * growing.
 *
 * With the GO_AOP_TRANSFORMATION_REPORT environment variable set to a file path, every case appends its numbers
 * to this file as a JSON line, for the comparison of two runs by compare-transformation-performance.php.
 */
#[Group('transformation-performance')]
final class TransformationPerformanceTest extends TestCase
{
    public const string REPORT_ENV = 'GO_AOP_TRANSFORMATION_REPORT';

    private const int ROUNDS = 15;

    private const string PHP_PARSER_CORPUS = __DIR__ . '/../../vendor/nikic/php-parser/lib/PhpParser';

    private const string FIXTURE_DIR = __DIR__ . '/../Instrument/Transformer/_files';

    /**
     * Weaving inputs of the WeavingTransformer snapshot tests
     */
    private const array WEAVING_FIXTURES = [
        'class',
        'class-typehint',
        'final-readonly-class',
        'import-collision',
        'multiple-classes',
        'multiple-ns',
        'php7-class',
        'php80-82-syntax',
        'php80-attribute-class',
        'php80-class-attribute',
        'php80-promoted-property',
        'php80-promoted-property-single-line',
        'php81-attr-args',
        'php81-enum',
        'php81-enum-const-expr',
        'php83-override',
    ];

    private static FileSystem $fileSystem;

    /**
     * Syntax trees of the corpus per name, parsed once for all cases
     *
     * @var array<string, list<StreamMetaData>>
     */
    private static array $corpora = [];

    public static function setUpBeforeClass(): void
    {
        self::$fileSystem = FileSystem::mount('transformationperf');
    }

    public static function tearDownAfterClass(): void
    {
        self::$fileSystem->unmount();
        self::$corpora = [];
        MagicConstantTransformer::reset();
        FilterInjectorTransformer::reset();
        ConstructorExecutionTransformer::reset();
    }

    /**
     * @return iterable<string, array{bool, float}>
     */
    public static function ruleSets(): iterable
    {
        // Most files contain no marker of the default rule, their syntax trees are not walked at all
        yield 'default rules' => [false, 0.4];
        yield 'all rules' => [true, 1.7];
    }

    /**
     * @param bool  $allRules Enables the rules of INTERCEPT_INITIALIZATIONS and INTERCEPT_INCLUDES too
     * @param float $maxRatio Budget: transformation time / plain walk time
     */
    #[DataProvider('ruleSets')]
    public function testSyntaxTreeRewriterStaysWithinBudget(bool $allRules, float $maxRatio): void
    {
        $kernel = $this->createKernel();
        $rules  = [];
        if ($allRules) {
            $rules[] = new ConstructorExecutionTransformer();
            $rules[] = new FilterInjectorTransformer($kernel, 'go.source.transforming.loader', new CachePathManager($kernel));
        }
        $rules[] = new MagicConstantTransformer($kernel);

        $this->assertTransformationWithinBudget(self::loadPhpParserCorpus(), new SyntaxTreeRewriter(...$rules), $maxRatio);
    }

    /**
     * Every method of every class in the weaving fixtures is intercepted by one before advice
     */
    public function testWeavingTransformerStaysWithinBudget(): void
    {
        $kernel        = $this->createKernel();
        $adviceMatcher = $this->createStub(AdviceMatcherInterface::class);
        $adviceMatcher->method('getAdvicesForClass')->willReturnCallback(static function (ReflectionClass $class): array {
            $advices = [];
            foreach ($class->getMethods() as $method) {
                $advisorId = "advisor.{$class->name}->{$method->name}";
                $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = new BeforeInterceptor(static function (): void {});
            }

            return $advices;
        });
        $transformer = new WeavingTransformer(
            $kernel,
            $adviceMatcher,
            new CachePathManager($kernel),
            $this->createStub(AspectLoader::class),
        );

        $this->assertTransformationWithinBudget(self::loadWeavingCorpus(), $transformer, 0.8);
    }

    /**
     * @param list<StreamMetaData> $corpus   Files to transform
     * @param float                $maxRatio Budget: transformation time / plain walk time
     */
    private function assertTransformationWithinBudget(array $corpus, SourceTransformer $transformer, float $maxRatio): void
    {
        $traverser = new NodeTraverser(new class extends NodeVisitorAbstract {});
        [$plainTime, $time] = self::measure(
            self::loadPhpParserCorpus(),
            static fn(StreamMetaData $file): mixed => $traverser->traverse($file->syntaxTree),
            $corpus,
            static fn(StreamMetaData $file): mixed => $transformer->transform($file),
        );
        $ratio     = $time / $plainTime;

        fwrite(STDERR, sprintf(
            "\n%-40s plain walk %7.2f ms, transformation %7.2f ms, ratio %5.2f (budget %5.2f)",
            $this->name() . ' ' . $this->dataName(),
            $plainTime,
            $time,
            $ratio,
            $maxRatio,
        ));
        $reportFile = getenv(self::REPORT_ENV);
        if (is_string($reportFile) && $reportFile !== '') {
            $report = ['case' => $this->name() . ' ' . $this->dataName(), 'plain' => $plainTime, 'time' => $time, 'ratio' => $ratio];
            file_put_contents($reportFile, json_encode($report, JSON_THROW_ON_ERROR) . "\n", FILE_APPEND);
        }
        $this->assertLessThanOrEqual($maxRatio, $ratio);
    }

    /**
     * Returns the median times of the plain walk and of the transformation over several rounds, in milliseconds
     *
     * Every round runs both, so a change of the machine speed during the run affects both alike.
     *
     * @param list<StreamMetaData>           $plainCorpus
     * @param Closure(StreamMetaData): mixed $plainWalk
     * @param list<StreamMetaData>           $corpus
     * @param Closure(StreamMetaData): mixed $transform
     *
     * @return array{float, float}
     */
    private static function measure(array $plainCorpus, Closure $plainWalk, array $corpus, Closure $transform): array
    {
        $originalTokens = array_map(static fn(StreamMetaData $file): array => $file->tokenStream, $corpus);
        $plainTimes     = [];
        $times          = [];
        // The first round warms up the caches and is not counted
        for ($round = 0; $round <= self::ROUNDS; $round++) {
            foreach ($corpus as $index => $file) {
                $file->tokenStream = array_map(static fn(PhpToken $token): PhpToken => clone $token, $originalTokens[$index]);
            }
            $plainTime = self::time($plainCorpus, $plainWalk);
            $time      = self::time($corpus, $transform);
            if ($round > 0) {
                $plainTimes[] = $plainTime;
                $times[]      = $time;
            }
        }
        foreach ($corpus as $index => $file) {
            $file->tokenStream = $originalTokens[$index];
        }

        assert($plainTimes !== [] && $times !== []);

        return [self::median($plainTimes), self::median($times)];
    }

    /**
     * Returns the time of one pass over the corpus, in nanoseconds
     *
     * @param list<StreamMetaData>           $corpus
     * @param Closure(StreamMetaData): mixed $pass
     */
    private static function time(array $corpus, Closure $pass): int
    {
        gc_collect_cycles();
        gc_disable();
        $start = hrtime(true);
        foreach ($corpus as $file) {
            $pass($file);
        }
        $elapsed = hrtime(true) - $start;
        gc_enable();

        return $elapsed;
    }

    /**
     * Returns the median of the times in nanoseconds, in milliseconds
     *
     * @param non-empty-list<int> $times
     */
    private static function median(array $times): float
    {
        sort($times);

        return $times[intdiv(count($times), 2)] / 1e6;
    }

    /**
     * Sources of nikic/php-parser: a large real-world code base, pinned by the installed version
     *
     * @return list<StreamMetaData>
     */
    private static function loadPhpParserCorpus(): array
    {
        if (!isset(self::$corpora['php-parser'])) {
            $fileNames = [];
            $files     = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PHP_PARSER_CORPUS));
            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $fileNames[] = $file->getPathname();
                }
            }
            sort($fileNames);
            self::$corpora['php-parser'] = array_map(self::loadFile(...), $fileNames);
        }

        return self::$corpora['php-parser'];
    }

    /**
     * @return list<StreamMetaData>
     */
    private static function loadWeavingCorpus(): array
    {
        if (!isset(self::$corpora['weaving'])) {
            $fileNames = array_map(static fn(string $name): string => self::FIXTURE_DIR . "/{$name}.php", self::WEAVING_FIXTURES);
            self::$corpora['weaving'] = array_map(self::loadFile(...), $fileNames);
        }

        return self::$corpora['weaving'];
    }

    private static function loadFile(string $fileName): StreamMetaData
    {
        $stream = fopen($fileName, 'rb');
        self::assertIsResource($stream);
        $source = stream_get_contents($stream);
        self::assertIsString($source);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

    private function createKernel(): AspectKernel
    {
        $container = $this->createStub(AspectContainer::class);
        $container->method('getServicesByInterface')->willReturn([]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->method('getOptions')->willReturn([
            'debug'          => false,
            'appDir'         => dirname(__DIR__),
            'cacheDir'       => self::$fileSystem->path('/'),
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => AspectContainer::class,
            'driver'         => WeavingDriver::Stream,
        ]);

        return $kernel;
    }
}
