<?php
declare(strict_types=1);

// Set a default for the application base path and public path if they are missing...
$_SERVER['APP_BASE_PATH'] = $_ENV['APP_BASE_PATH'] ?? $_SERVER['APP_BASE_PATH'] ?? __DIR__ . '/..';
$_SERVER['APP_PUBLIC_PATH'] = $_ENV['APP_PUBLIC_PATH'] ?? $_SERVER['APP_PUBLIC_PATH'] ?? __DIR__;

// The Stimulus FrankenPHP worker runtime entry lands in P8; this published
// stub resolves it from the installed plugin once the runtime ships.
require __DIR__ . '/../vendor/crustum/stimulus/bin/frankenphp-worker.php';
