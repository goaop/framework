<?php
declare(strict_types = 1);

/**
 * Probe of the weaving drivers, run in a subprocess by the functional tests of a driver package (the same
 * scenario under the ffi driver and under a source-transforming driver, compared)
 *
 * Usage: GO_AOP_CONFIGURATION=<name> php -d opcache.jit=off -d ffi.enable=1 driver-weaving.php <scenario>
 * Prints one JSON report: what the kernel booted, which classes were woven, the results and the advice log
 * of the scenario, or the exception that ended it.
 */

include_once __DIR__ . '/../../../../vendor/autoload.php';

use Go\Aop\Proxy;
use Go\Core\AspectKernel;
use Go\Driver\Ffi\ClassWeaver;
use Go\Driver\Ffi\FfiDriver;
use Go\Tests\TestProject\Application\FfiChild;
use Go\Tests\TestProject\Application\FfiFunctions;
use Go\Tests\TestProject\Application\FfiLate;
use Go\Tests\TestProject\Application\FfiParent;
use Go\Tests\TestProject\Application\FfiUnadvised;
use Go\Tests\TestProject\Application\FfiUnsupported;
use Go\Tests\TestProject\Aspect\FfiAspect;

$configurationName = ($env = getenv('GO_AOP_CONFIGURATION')) ? $env : 'ffi';
$scenario          = $argv[1] ?? 'weave';
$settings          = require __DIR__ . '/../web/configuration.php';
$configuration     = $settings[$configurationName];
$cacheDir          = $configuration['cacheDir'];

$report = [
    'scenario'      => $scenario,
    'configuration' => $configurationName,
    'php'           => PHP_VERSION,
    'opcache'       => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
    'indexExisted'  => is_file($cacheDir . '/_ffi.cache'),
];

$describeWeaving = static function (AspectKernel $kernel, string ...$classes) use (&$report): void {
    if ($kernel->getOptions()['driver'] !== FfiDriver::class) {
        return;
    }
    $weaver = $kernel->getContainer()->getService(ClassWeaver::class);
    foreach ($classes as $class) {
        $result = $weaver->weave($class);
        $report['weave'][$class] = ['outcome' => $result->outcome->value, 'methods' => $result->methods, 'reason' => $result->reason];
    }
};

try {
    if ($scenario === 'late') {
        // Declared before the kernel exists, so never seen by the kernel's class loader
        require __DIR__ . '/../src/Application/FfiLate.php';
    }

    /** @var AspectKernel $kernel */
    $kernel = $configuration['kernel']::getInstance();
    // The test-harness keys (kernel class, console and front-controller paths) are not kernel options
    $kernel->init(array_diff_key($configuration, array_flip(['kernel', 'console', 'frontController'])));
    $container        = $kernel->getContainer();
    $report['driver'] = $kernel->getOptions()['driver'];
    if ($report['driver'] === FfiDriver::class) {
        $report['boot'] = FfiDriver::getBootReport($container);
    }

    switch ($scenario) {
        case 'weave':
            $parent = new FfiParent();
            $child  = new FfiChild();
            $report['calls'] = [
                'parent.greet'      => $parent->greet('Ann'),
                'child.greet'       => $child->greet('Bob'),
                'child.describe'    => $child->describe()::class,
                'child.create'      => FfiChild::create('Cid')::class,
                'child.callSecret'  => $child->callSecret(),
                'child.withDefault' => $child->withDefault(),
                'child.withOther'   => $child->withDefault(2, $child),
                'child.getCalls'    => $child->getCalls(),
                'unadvised.ping'    => (new FfiUnadvised())->ping(),
            ];
            $report['reflection'] = [
                'child.describe.class' => (new ReflectionMethod(FfiChild::class, 'describe'))->class,
                'child.greet.class'    => (new ReflectionMethod(FfiChild::class, 'greet'))->class,
                'parent.greet.file'    => basename((string) (new ReflectionMethod(FfiParent::class, 'greet'))->getFileName()),
                'child.alias'          => method_exists($child, 'greetOriginalAlias'),
                'parent.alias'         => method_exists($parent, 'greetOriginalAlias'),
                'child.proxy'          => $child instanceof Proxy,
                'unadvised.proxy'      => new FfiUnadvised() instanceof Proxy,
            ];
            $describeWeaving($kernel, FfiParent::class, FfiChild::class, FfiUnadvised::class);
            break;

        case 'late':
            $describeWeaving($kernel, FfiLate::class);
            $late = new FfiLate();
            $report['calls'] = ['late.compute' => $late->compute(2, 3)];
            break;

        case 'unsupported':
            $report['loaded'] = class_exists(FfiUnsupported::class);
            break;

        case 'functions':
            $functions = new FfiFunctions();
            $report['calls'] = [
                'functions.total'          => $functions->total([1, 2, 3]),
                'functions.totalQualified' => $functions->totalQualified([4, 5]),
                'functions.proxied'        => function_exists('Go\\Tests\\TestProject\\Application\\array_sum'),
            ];
            $describeWeaving($kernel, FfiFunctions::class);
            break;

        default:
            throw new InvalidArgumentException('Unknown scenario ' . $scenario);
    }
} catch (Throwable $exception) {
    $report['exception'] = [
        'class'   => $exception::class,
        'message' => $exception->getMessage(),
        'previous' => $exception->getPrevious() !== null ? $exception->getPrevious()::class : null,
    ];
}

$report['log']    = FfiAspect::$log;
$report['donors'] = array_values(array_filter(
    [FfiParent::class, FfiChild::class, FfiLate::class, FfiUnadvised::class],
    static fn(string $class): bool => trait_exists($class . 'OriginalTrait', false),
));
$cacheFiles = [];
if (is_dir($cacheDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $cacheFiles[] = str_replace('\\', '/', substr((string) $file, strlen($cacheDir) + 1));
    }
    sort($cacheFiles);
}
$report['cache'] = $cacheFiles;

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
