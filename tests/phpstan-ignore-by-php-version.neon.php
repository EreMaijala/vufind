<?php

declare(strict_types=1);

// See https://github.com/phpstan/phpstan-src/blob/1.9.x/build/ignore-by-php-version.neon.php for more examples.

$includes = [];
if (PHP_VERSION_ID < 80400) {
    $includes[] = __DIR__ . '/phpstan-ignore-pre-8-4-pdo.neon';
}

return [
    'includes' => $includes,
];
