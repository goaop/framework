<?php
declare(strict_types=1);

// Plain file including another one through a relative segment: woven by Features::INTERCEPT_INCLUDES
return ['file' => __FILE__, 'plain' => require __DIR__ . '/../includes/plain-file.php'];
