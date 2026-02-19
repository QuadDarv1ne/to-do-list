<?php

/**
 * Simple health check script to verify basic functionality
 * Run: php tests/HealthCheck.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

echo "🏥 Health Check for To-Do List Application\n";
echo "==========================================\n\n";

$checks = [
    'PHP Version' => function () {
        $version = PHP_VERSION;
        $required = '8.5.0';
        $ok = version_compare($version, $required, '>=');

        return [$ok, "Current: $version, Required: >= $required"];
    },

    'Composer Autoload' => function () {
        return [class_exists('Symfony\\Component\\HttpKernel\\Kernel'), 'Symfony classes loaded'];
    },

    'Environment File' => function () {
        return [file_exists(__DIR__ . '/../.env'), '.env file exists'];
    },

    'Database URL' => function () {
        $dbUrl = $_ENV['DATABASE_URL'] ?? null;

        return [$dbUrl !== null, $dbUrl ? 'Configured' : 'Not configured'];
    },

    'Var Directory' => function () {
        $varDir = __DIR__ . '/../var';
        $writable = is_dir($varDir) && is_writable($varDir);

        return [$writable, $writable ? 'Writable' : 'Not writable'];
    },

    'Cache Directory' => function () {
        $cacheDir = __DIR__ . '/../var/cache';
        $exists = is_dir($cacheDir);

        return [$exists, $exists ? 'Exists' : 'Not found'];
    },

    'Public Directory' => function () {
        $publicDir = __DIR__ . '/../public';
        $exists = is_dir($publicDir) && file_exists($publicDir . '/index.php');

        return [$exists, $exists ? 'Ready' : 'Missing index.php'];
    },

    'Vendor Directory' => function () {
        $vendorDir = __DIR__ . '/../vendor';
        $exists = is_dir($vendorDir);

        return [$exists, $exists ? 'Dependencies installed' : 'Run composer install'];
    },
];

$passed = 0;
$failed = 0;

foreach ($checks as $name => $check) {
    try {
        [$ok, $message] = $check();

        if ($ok) {
            echo "✅ $name: $message\n";
            $passed++;
        } else {
            echo "❌ $name: $message\n";
            $failed++;
        }
    } catch (\Exception $e) {
        echo "❌ $name: Error - " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n==========================================\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed === 0) {
    echo "✅ All checks passed! Application is healthy.\n";
    exit(0);
} else {
    echo "⚠️  Some checks failed. Please review the issues above.\n";
    exit(1);
}
