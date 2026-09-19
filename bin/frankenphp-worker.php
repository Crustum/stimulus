<?php
declare(strict_types=1);

use Cake\Http\ResponseEmitter;
use Cake\Log\Log;
use Crustum\Stimulus\Bridge\FrankenPhp\FrankenPhpClient;
use Crustum\Stimulus\Http\RequestContext;
use Crustum\Stimulus\Http\Stream;
use Crustum\Stimulus\Worker\ApplicationFactory;
use Crustum\Stimulus\Worker\Worker;
use Laminas\Diactoros\Response\TextResponse;

if ((!($_SERVER['FRANKENPHP_WORKER'] ?? false)) || !function_exists('frankenphp_handle_request')) {
    echo 'FrankenPHP must be in worker mode to use this script.';

    exit(1);
}

ignore_user_abort(true);

$basePath = require __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Start The Stimulus Worker
|--------------------------------------------------------------------------
|
| Next we will start the Stimulus worker, which is a long running process to
| handle incoming requests to the application. This worker will be used
| by FrankenPHP to serve an entire CakePHP application at high speed.
|
*/

$appClass = $_SERVER['STIMULUS_APP_CLASS'] ?? $_ENV['STIMULUS_APP_CLASS'] ?? 'App\\Application';
$configDir = $_SERVER['STIMULUS_CONFIG_DIR'] ?? $_ENV['STIMULUS_CONFIG_DIR'] ?? $basePath . '/config';

$frankenPhpClient = new FrankenPhpClient();

try {
    $worker = new Worker(
        new ApplicationFactory($appClass, $configDir),
        $frankenPhpClient,
    );

    $worker->boot();
} catch (Throwable $e) {
    Log::error($e::class . ': ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    $errorStream = defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');

    if (is_resource($errorStream)) {
        fwrite($errorStream, sprintf(
            "[stimulus bootstrap] %s: %s\n  in %s:%d\n%s\n",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    exit(1);
}

$requestCount = 0;
$debugMode = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';
$maxRequests = $_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS'] ?? 1000;
$requestMaxExecutionTime = $_ENV['REQUEST_MAX_EXECUTION_TIME'] ?? $_SERVER['REQUEST_MAX_EXECUTION_TIME'] ?? null;

if (PHP_OS_FAMILY === 'Linux' && !is_null($requestMaxExecutionTime)) {
    set_time_limit((int)$requestMaxExecutionTime);
}

try {
    $handleRequest = static function () use ($worker, $frankenPhpClient, $debugMode): void {
        try {
            [$request, $context] = $frankenPhpClient->marshalRequest(new RequestContext());

            $worker->handle($request, $context);
        } catch (Throwable $e) {
            Log::error($e::class . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            (new ResponseEmitter())->emit(new TextResponse(
                $debugMode === 'true' ? (string)$e : 'Internal Server Error',
                500,
                [
                    'Status' => '500 Internal Server Error',
                    'Content-Type' => 'text/plain',
                ],
            ));

            Stream::shutdown($e);
        }
    };

    while ($requestCount < $maxRequests && frankenphp_handle_request($handleRequest)) {
        $requestCount++;
    }
} finally {
    $worker->terminate();

    gc_collect_cycles();
}
