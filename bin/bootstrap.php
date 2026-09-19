<?php
declare(strict_types=1);

ini_set('display_errors', 'stderr');

$_ENV['APP_RUNNING_IN_CONSOLE'] = false;

/*
|--------------------------------------------------------------------------
| Normalize Process Environment
|--------------------------------------------------------------------------
|
| Hosts without the `E` flag in `variables_order` (like production PHP
| builds) leave `$_ENV` empty even when the process environment carries
| values, so every real environment variable lands in both superglobals
| before the workers read them. Existing server entries keep their values.
|
*/

foreach ((array)getenv() as $name => $value) {
    if (is_string($value)) {
        $_ENV[$name] = $value;
        $_SERVER[$name] ??= $value;
    }
}

/*
|--------------------------------------------------------------------------
| Find Application Base Path
|--------------------------------------------------------------------------
|
| First we need to locate the path to the application bootstrapper, which
| is able to create a fresh copy of the CakePHP application for us and
| we can use this to handle requests. For now we just need the path.
|
*/

$basePath = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH');

if (!is_string($basePath) || $basePath === '') {
    echo 'Cannot find application base path.';

    exit(11);
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/

$vendorDir = $_SERVER['COMPOSER_VENDOR_DIR'] ?? $_ENV['COMPOSER_VENDOR_DIR'] ?? getenv('COMPOSER_VENDOR_DIR') ?: "{$basePath}/vendor";

if (!is_file($autoload_file = "{$vendorDir}/autoload.php")) {
    echo "Composer autoload file was not found. Did you install the project's dependencies?";

    exit(10);
}

require_once $autoload_file;

return $basePath;
