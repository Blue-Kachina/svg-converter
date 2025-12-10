<?php
/**
 * PHPUnit bootstrap file to ensure the SQLite test database file exists
 * before Laravel boots. This makes `php artisan test` and CI runs robust
 * even in a clean workspace.
 */

// Attempt to include Composer's autoloader. Support running from root or from tests dir.
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        break;
    }
}

// Resolve project root (directory containing composer.json)
$projectRoot = dirname(__DIR__);
if (!file_exists($projectRoot . DIRECTORY_SEPARATOR . 'composer.json')) {
    // Fallback: one level up if structure differs
    $projectRoot = dirname($projectRoot);
}

// Ensure database directory exists
$dbDir = $projectRoot . DIRECTORY_SEPARATOR . 'database';
if (!is_dir($dbDir)) {
    @mkdir($dbDir, 0777, true);
}

// Ensure the SQLite file exists
$dbFile = $dbDir . DIRECTORY_SEPARATOR . 'database.sqlite';
if (!file_exists($dbFile)) {
    @touch($dbFile);
}
