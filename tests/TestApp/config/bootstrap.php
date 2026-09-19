<?php
declare(strict_types=1);

use Cake\Core\Configure;

/*
 * Test application bootstrap loaded by worker entries on every boot.
 * Carries the identifiers controller dispatch needs without the PHPUnit
 * bootstrap, which real host applications provide through their own
 * bootstrap files. It also owns the framework files the slim worker entry
 * deliberately skips: the entry loads the composer autoloader only, so the
 * global helpers, the initial router state, and the config constant land
 * here exactly like a stock host `config/bootstrap.php` provides them.
 * Every load stays guarded so the PHPUnit bootstrap, which defines the
 * same constants first, never collides with worker boots.
 */

$testVendorDir = $_SERVER['COMPOSER_VENDOR_DIR']
    ?? $_ENV['COMPOSER_VENDOR_DIR']
    ?? getenv('COMPOSER_VENDOR_DIR')
    ?: dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor';

$testFunctionsFile = $testVendorDir . DIRECTORY_SEPARATOR
    . 'cakephp' . DIRECTORY_SEPARATOR
    . 'cakephp' . DIRECTORY_SEPARATOR
    . 'src' . DIRECTORY_SEPARATOR . 'functions.php';

if (is_file($testFunctionsFile) && !function_exists('env')) {
    require_once $testFunctionsFile;
}

$testCoreBootstrap = $testVendorDir . DIRECTORY_SEPARATOR
    . 'cakephp' . DIRECTORY_SEPARATOR
    . 'cakephp' . DIRECTORY_SEPARATOR
    . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (is_file($testCoreBootstrap) && !defined('TIME_START')) {
    require_once $testCoreBootstrap;
}

if (!defined('CONFIG')) {
    define('CONFIG', __DIR__ . DIRECTORY_SEPARATOR);
}

Configure::write('App.namespace', 'TestApp');
Configure::write('App.encoding', 'UTF-8');
