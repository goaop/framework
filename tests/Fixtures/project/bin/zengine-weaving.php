<?php
declare(strict_types = 1);

/**
 * Probe of the z-engine weaving driver, run in a subprocess by the functional tests (tests/Functional/ZEngine)
 *
 * Usage: GO_AOP_CONFIGURATION=<name> php -d opcache.jit=off -d ffi.enable=1 zengine-weaving.php <scenario>
 * Prints one JSON report: what the kernel booted, which classes were woven, the results and the advice log
 * of the scenario, or the exception that ended it.
 */

include_once __DIR__ . '/../../../../vendor/autoload.php';

use Go\Core\AspectKernel;
use Go\Instrument\ZEngine\ZEngineClassWeaver;
use Go\Instrument\ZEngine\ZEngineDriver;
use Go\Tests\TestProject\Application\ZEngineChild;
use Go\Tests\TestProject\Application\ZEngineLate;
use Go\Tests\TestProject\Application\ZEngineParent;
use Go\Tests\TestProject\Application\ZEngineUnadvised;
use Go\Tests\TestProject\Application\ZEngineUnsupported;
use Go\Tests\TestProject\Aspect\ZEngineAspect;

$configurationName = ($env = getenv('GO_AOP_CONFIGURATION')) ? $env : 'zengine';
$scenario          = $argv[1] ?? 'weave';
$settings          = require __DIR__ . '/../web/configuration.php';
$configuration     = $settings[$configurationName];
$cacheDir          = $configuration['cacheDir'];

$report = [
    'scenario'      => $scenario,
    'configuration' => $configurationName,
    'php'           => PHP_VERSION,
    'opcache'       => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
    'indexExisted'  => is_file($cacheDir . '/_zengine.cache'),
];

$describeWeaving = static function (AspectKernel $kernel, string ...$classes) use (&$report): void {
    if ($kernel->getOptions()['driver']->value !== 'zengine') {
        return;
    }
    $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);
    foreach ($classes as $class) {
        $result = $weaver->weave($class);
        $report['weave'][$class] = ['outcome' => $result->outcome->value, 'methods' => $result->methods, 'reason' => $result->reason];
    }
};

try {
    if ($scenario === 'late') {
        // Declared before the kernel exists, so never seen by the kernel's class loader
        require __DIR__ . '/../src/Application/ZEngineLate.php';
    }

    /** @var AspectKernel $kernel */
    $kernel = $configuration['kernel']::getInstance();
    // The test-harness keys (kernel class, console and front-controller paths) are not kernel options
    $kernel->init(array_diff_key($configuration, array_flip(['kernel', 'console', 'frontController'])));
    $container        = $kernel->getContainer();
    $report['driver'] = $kernel->getOptions()['driver']->value;
    if ($report['driver'] === 'zengine') {
        $report['boot'] = ZEngineDriver::getBootReport($container);
    }

    switch ($scenario) {
        case 'weave':
            $parent = new ZEngineParent();
            $child  = new ZEngineChild();
            $report['calls'] = [
                'parent.greet'      => $parent->greet('Ann'),
                'child.greet'       => $child->greet('Bob'),
                'child.describe'    => $child->describe()::class,
                'child.create'      => ZEngineChild::create('Cid')::class,
                'child.callSecret'  => $child->callSecret(),
                'child.withDefault' => $child->withDefault(),
                'child.withOther'   => $child->withDefault(2, $child),
                'child.getCalls'    => $child->getCalls(),
                'unadvised.ping'    => (new ZEngineUnadvised())->ping(),
            ];
            $report['reflection'] = [
                'child.describe.class' => (new ReflectionMethod(ZEngineChild::class, 'describe'))->class,
                'child.greet.class'    => (new ReflectionMethod(ZEngineChild::class, 'greet'))->class,
                'parent.greet.file'    => basename((string) (new ReflectionMethod(ZEngineParent::class, 'greet'))->getFileName()),
                'child.alias'          => method_exists($child, 'greetOriginalAlias'),
                'parent.alias'         => method_exists($parent, 'greetOriginalAlias'),
            ];
            $describeWeaving($kernel, ZEngineParent::class, ZEngineChild::class, ZEngineUnadvised::class);
            break;

        case 'late':
            $describeWeaving($kernel, ZEngineLate::class);
            $late = new ZEngineLate();
            $report['calls'] = ['late.compute' => $late->compute(2, 3)];
            break;

        case 'unsupported':
            $report['loaded'] = class_exists(ZEngineUnsupported::class);
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

$report['log']    = ZEngineAspect::$log;
$report['donors'] = array_values(array_filter(
    [ZEngineParent::class, ZEngineChild::class, ZEngineLate::class, ZEngineUnadvised::class],
    static fn(string $class): bool => class_exists($class . '__AopDonor', false),
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
