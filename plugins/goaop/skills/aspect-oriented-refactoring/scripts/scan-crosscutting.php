<?php

/**
 * Finds candidates for aspects: statements that repeat across methods of the scanned directories.
 *
 * Usage: php scan-crosscutting.php [--autoload=vendor/autoload.php] [--min=3] [--format=text|json] <dir> [<dir>...]
 *
 * It needs nikic/php-parser 5, which every project with goaop/framework has in vendor/. The result is a list of
 * candidates to read and judge, not a list of things to refactor: see SKILL.md.
 */

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

$options = ['autoload' => null, 'min' => 3, 'format' => 'text'];
$directories = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(autoload|min|format)=(.+)$/', $argument, $match)) {
        $options[$match[1]] = $match[2];
    } else {
        $directories[] = $argument;
    }
}
if ($directories === []) {
    fwrite(STDERR, "Usage: php scan-crosscutting.php [--autoload=FILE] [--min=N] [--format=text|json] <dir> [<dir>...]\n");
    exit(2);
}

// Composer's autoloader of the scanned project: given, or the nearest vendor/ above the working directory
$autoload = $options['autoload'];
for ($directory = getcwd(); $autoload === null && $directory !== false && $directory !== dirname($directory); $directory = dirname($directory)) {
    if (is_file($directory . '/vendor/autoload.php')) {
        $autoload = $directory . '/vendor/autoload.php';
    }
}
if ($autoload === null || !is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php was not found, pass it with --autoload=FILE\n");
    exit(2);
}
require $autoload;
if (!class_exists(ParserFactory::class) || !method_exists(ParserFactory::class, 'createForHostVersion')) {
    fwrite(STDERR, "nikic/php-parser 5 is required (installed with goaop/framework)\n");
    exit(2);
}

/** Replaces what differs between copies of the same code: variable names, strings and numbers */
final class FragmentNormalizer extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name) && $node->name !== 'this') {
            return new Node\Expr\Variable('v');
        }
        if ($node instanceof Node\Scalar\String_) {
            return new Node\Scalar\String_('S');
        }
        if ($node instanceof Node\Scalar\InterpolatedString) {
            return new Node\Scalar\String_('S');
        }
        if ($node instanceof Node\Scalar\Int_ || $node instanceof Node\Scalar\Float_) {
            return new Node\Scalar\Int_(0);
        }

        return null;
    }
}

final class CrosscuttingScanner
{
    private const array CONCERNS = [
        'transaction'   => '/beginTransaction|->commit\(|rollBack|wrapInTransaction|transactional\(|DB::transaction/i',
        'logging'       => '/->(log|debug|info|notice|warning|error|critical|alert|emergency)\(|\bLog::|error_log\(|logger/i',
        'authorization' => '/isGranted|denyAccessUnless|authorize\(|->can\(|hasRole|checkPermission|AccessDenied|Gate::/i',
        'caching'       => '/cache|->getItem\(|->remember\(|apcu_/i',
        'timing'        => '/microtime\(|hrtime\(|stopwatch|->start\(|->stop\(/i',
        'metrics'       => '/->(increment|inc|observe|timing|gauge|histogram|counter)\(|statsd|metrics/i',
        'retry'         => '/\b(attempt|retry|retries|tries)\b|usleep\(|sleep\(/i',
        'events'        => '/->dispatch\(|->fire\(|->emit\(|event\(/i',
        'locking'       => '/->(lock|acquire|release|unlock)\(|flock\(/i',
        'validation'    => '/InvalidArgument|assert\(|Assert::|->validate\(/i',
    ];

    /** @var array<string, array{code: string, kind: string, occurrences: list<array<string, mixed>>}> */
    private array $groups = [];
    private NodeFinder $finder;
    private Standard $printer;

    public function __construct()
    {
        $this->finder  = new NodeFinder();
        $this->printer = new Standard();
    }

    public function scanFile(string $file): void
    {
        try {
            $statements = (new ParserFactory())->createForHostVersion()->parse((string) file_get_contents($file));
        } catch (\PhpParser\Error $error) {
            fwrite(STDERR, "Skipped $file: {$error->getMessage()}\n");

            return;
        }
        $resolver = new NodeTraverser(new PhpParser\NodeVisitor\NameResolver());
        foreach ($this->finder->findInstanceOf($resolver->traverse($statements ?? []), Node\Stmt\ClassLike::class) as $class) {
            if ($class instanceof Node\Stmt\Interface_ || $class->name === null || $this->isAspect($class)) {
                continue;
            }
            $className = isset($class->namespacedName) ? $class->namespacedName->toString() : $class->name->toString();
            foreach ($class->getMethods() as $method) {
                if ($method->stmts !== null && $method->stmts !== []) {
                    $this->scanMethod($file, $className . '::' . $method->name->toString(), $method->stmts);
                }
            }
        }
    }

    /** Aspects are the result of the refactoring: their advices repeat joinpoint calls by design */
    private function isAspect(Node\Stmt\ClassLike $class): bool
    {
        $interfaces = $class instanceof Node\Stmt\Class_ || $class instanceof Node\Stmt\Enum_ ? $class->implements : [];

        return array_any($interfaces, static fn(Node\Name $name): bool => ltrim($name->toString(), '\\') === 'Go\\Aop\\Aspect');
    }

    /** @param list<Node\Stmt> $statements Top-level statements of one method */
    private function scanMethod(string $file, string $method, array $statements): void
    {
        $statements = array_values(array_filter($statements, static fn(Node $s): bool => !$s instanceof Node\Stmt\Nop));
        $last = count($statements) - 1;
        // A trailing "return $result;" does not count: the statement before it is the last one that does work
        if ($last > 0 && $statements[$last] instanceof Node\Stmt\Return_) {
            $last--;
        }
        foreach ($statements as $index => $statement) {
            if ($statement instanceof Node\Stmt\Return_ && $index > $last) {
                continue;
            }
            $position = match (true) {
                $index === 0     => 'start',
                $index === $last => 'end',
                $index <= 2      => 'near start',
                default          => 'middle',
            };
            if ($statement instanceof Node\Stmt\TryCatch) {
                $this->record($this->tryShape($statement), 'try', 'wraps', $file, $method, $statement);
            } elseif ($this->isLoop($statement) && $this->finder->findFirstInstanceOf([$statement], Node\Stmt\TryCatch::class) !== null) {
                $this->record($this->normalize($statement), 'retry loop', 'wraps', $file, $method, $statement);
            } elseif ($this->isInteresting($statement)) {
                $this->record($this->normalize($statement), 'statement', $position, $file, $method, $statement);
            }
        }
    }

    private function isLoop(Node $statement): bool
    {
        return $statement instanceof Node\Stmt\While_ || $statement instanceof Node\Stmt\For_ || $statement instanceof Node\Stmt\Do_;
    }

    /** A statement that does something: it calls, creates or throws. Plain assignments and returns repeat by nature */
    private function isInteresting(Node\Stmt $statement): bool
    {
        if ($statement instanceof Node\Stmt\Return_) {
            return false;
        }

        return $this->finder->findFirst([$statement], static fn(Node $node): bool => $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\NullsafeMethodCall
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\FuncCall
            || $node instanceof Node\Expr\Throw_) !== null;
    }

    /** The handlers of a try statement without its body: what is repeated is how failures are treated */
    private function tryShape(Node\Stmt\TryCatch $try): string
    {
        $shape = clone $try;
        $shape->stmts = [];

        return str_replace("try {\n}", 'try { ... }', $this->normalize($shape));
    }

    private function normalize(Node $node): string
    {
        $traverser = new NodeTraverser(new PhpParser\NodeVisitor\CloningVisitor(), new FragmentNormalizer());

        return $this->printer->prettyPrint($traverser->traverse([$node]));
    }

    private function record(string $fingerprint, string $kind, string $position, string $file, string $method, Node $node): void
    {
        $this->groups[$fingerprint] ??= ['code' => $fingerprint, 'kind' => $kind, 'occurrences' => []];
        $this->groups[$fingerprint]['occurrences'][] = [
            'file'     => $file,
            'line'     => $node->getStartLine(),
            'method'   => $method,
            'position' => $position,
        ];
    }

    /** @return list<array<string, mixed>> Candidates, the most repeated first */
    public function candidates(int $minimum): array
    {
        $candidates = [];
        foreach ($this->groups as $group) {
            $methods = array_unique(array_column($group['occurrences'], 'method'));
            if (count($methods) < $minimum) {
                continue;
            }
            $positions = array_count_values(array_column($group['occurrences'], 'position'));
            arsort($positions);
            $concerns = array_keys(array_filter(self::CONCERNS, static fn(string $pattern): bool => preg_match($pattern, $group['code']) === 1));
            $candidates[] = [
                'concerns'    => $concerns === [] ? ['unknown'] : $concerns,
                'kind'        => $group['kind'],
                'methods'     => count($methods),
                'classes'     => count(array_unique(array_map(static fn(string $m): string => explode('::', $m)[0], $methods))),
                'positions'   => $positions,
                'advice'      => $this->suggestAdvice($group['kind'], (string) array_key_first($positions), $group['code']),
                'code'        => $group['code'],
                'occurrences' => $group['occurrences'],
            ];
        }
        usort($candidates, static fn(array $a, array $b): int => [$b['methods'], $b['classes']] <=> [$a['methods'], $a['classes']]);

        // Candidates found in exactly the same methods are often two halves of one concern (begin + commit, start + stop)
        foreach ($candidates as $number => $candidate) {
            $candidates[$number]['number']   = $number + 1;
            $candidates[$number]['together'] = [];
            foreach ($candidates as $other => $otherCandidate) {
                if ($other !== $number && $this->methodsOf($candidate) === $this->methodsOf($otherCandidate)) {
                    $candidates[$number]['together'][] = $other + 1;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, mixed> $candidate
     *
     * @return list<string>
     */
    private function methodsOf(array $candidate): array
    {
        $methods = array_values(array_unique(array_column($candidate['occurrences'], 'method')));
        sort($methods);

        return $methods;
    }

    /**
     * @param list<array<string, mixed>> $candidates
     *
     * @return array<string, list<int>> Methods that hold several candidates, the most tangled first
     */
    public function tangledMethods(array $candidates): array
    {
        $methods = [];
        foreach ($candidates as $candidate) {
            foreach ($this->methodsOf($candidate) as $method) {
                $methods[$method][] = $candidate['number'];
            }
        }
        $methods = array_filter($methods, static fn(array $numbers): bool => count($numbers) > 1);
        uasort($methods, static fn(array $a, array $b): int => count($b) <=> count($a));

        return $methods;
    }

    private function suggestAdvice(string $kind, string $position, string $code): string
    {
        if ($kind === 'retry loop') {
            return 'Around: the advice calls proceed() again';
        }
        if ($kind === 'try') {
            $onlyReports = preg_match('/catch[^{]*\{(?:(?!rollBack|return)[^}])*throw \$v;\s*\}/s', $code) === 1 && !str_contains($code, 'finally');

            return $onlyReports
                ? 'AfterThrowing when the handler only reports and rethrows; Around when it does anything else'
                : 'Around: try/catch/finally around proceed()';
        }

        return match ($position) {
            'start', 'near start' => 'Before, when it only needs the arguments and the object; Around when a value it computes is used later',
            'end'                 => 'After when it must run on failure too; Around when it needs the result or a value from the start',
            default               => 'In the middle now. If what precedes it is another concern, it reaches the boundary once that one is extracted; otherwise extract the surrounding steps into a method and advise that method',
        };
    }
}

$scanner = new CrosscuttingScanner();
$files = 0;
foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        fwrite(STDERR, "Not a directory: $directory\n");
        exit(2);
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $scanner->scanFile($file->getPathname());
            $files++;
        }
    }
}
$candidates = $scanner->candidates(max(2, (int) $options['min']));

if ($options['format'] === 'json') {
    echo json_encode(['files' => $files, 'candidates' => $candidates, 'tangled_methods' => $scanner->tangledMethods($candidates)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

printf("Scanned %d files, %d candidates repeated in at least %d methods\n", $files, count($candidates), max(2, (int) $options['min']));
foreach ($candidates as $number => $candidate) {
    $positions = implode(', ', array_map(static fn(string $p, int $n): string => "$p ×$n", array_keys($candidate['positions']), $candidate['positions']));
    printf(
        "\n#%d  %s  (%s)  %d methods in %d classes\n    position: %s\n    advice:   %s\n%s",
        $candidate['number'],
        implode(' + ', $candidate['concerns']),
        $candidate['kind'],
        $candidate['methods'],
        $candidate['classes'],
        $positions,
        $candidate['advice'],
        $candidate['together'] === [] ? '' : '    together: in exactly the same methods as #' . implode(', #', $candidate['together']) . ": two halves of one concern (then one Around advice), or separate concerns of the same methods\n",
    );
    echo '    | ', str_replace("\n", "\n    | ", $candidate['code']), "\n";
    foreach ($candidate['occurrences'] as $occurrence) {
        printf("    %s:%d  %s  [%s]\n", $occurrence['file'], $occurrence['line'], $occurrence['method'], $occurrence['position']);
    }
}

$tangled = $scanner->tangledMethods($candidates);
if ($tangled !== []) {
    echo "\nMethods with several candidates (the most tangled first)\n";
    foreach (array_slice($tangled, 0, 20, true) as $method => $numbers) {
        printf("    %s  #%s\n", $method, implode(', #', $numbers));
    }
}
