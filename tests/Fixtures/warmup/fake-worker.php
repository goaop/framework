<?php
declare(strict_types=1);

/**
 * Stand-in for a cache warmup worker, speaking the protocol without a kernel: answers every file with a record
 * holding its process id, fails files named `error*`, dies on files named `crash*`, and fails to boot like a real
 * worker when its first argument is `broken`. Prints stray output, which the coordinator must ignore.
 */

use Go\Instrument\ClassLoading\Warmup\WorkerProtocol;

require __DIR__ . '/../../../vendor/autoload.php';

if (($argv[1] ?? '') === 'broken') {
    fwrite(STDERR, "PHP Warning: something on the way\n");
    fwrite(STDOUT, WorkerProtocol::encode(['type' => 'failed', 'error' => 'Kernel boot failure']));
    exit(3);
}

echo "Stray output of the loader\n";
fwrite(STDOUT, WorkerProtocol::encode(['type' => 'ready']));

while (($line = fgets(STDIN)) !== false) {
    $file = WorkerProtocol::decode($line)['file'] ?? null;
    if (!is_string($file)) {
        continue;
    }
    $name = basename($file);
    if (str_starts_with($name, 'crash')) {
        fwrite(STDERR, "PHP Fatal error: weaving {$name} crashed\n");
        exit(255);
    }
    fwrite(STDOUT, WorkerProtocol::encode([
        'type'    => 'result',
        'file'    => $file,
        'error'   => str_starts_with($name, 'error') ? "Cannot weave {$name}" : null,
        'records' => ['/' . $name => ['pid' => getmypid()]],
    ]));
}
