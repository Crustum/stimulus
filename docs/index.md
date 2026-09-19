# CakePHP Stimulus Plugin

- [Introduction](#introduction)
- [Quickstart](#quickstart)
- [Installation](#installation)
- [Server Prerequisites](#server-prerequisites)
    - [FrankenPHP](#frankenphp)
    - [RoadRunner](#roadrunner)
    - [Swoole](#swoole)
    - [Open Swoole](#open-swoole)
- [Serving Your Application](#serving-your-application)
    - [Serving Your Application via HTTPS](#serving-your-application-via-https)
    - [Serving Your Application via Nginx](#serving-your-application-via-nginx)
    - [Watching for File Changes](#watching-for-file-changes)
    - [Specifying the Worker Count](#specifying-the-worker-count)
    - [Specifying the Max Request Count](#specifying-the-max-request-count)
    - [Specifying the Max Execution Time](#specifying-the-max-execution-time)
    - [Reloading the Workers](#reloading-the-workers)
    - [Stopping the Server](#stopping-the-server)
    - [Checking the Server Status](#checking-the-server-status)
- [Dependency Injection and Stimulus](#dependency-injection-and-stimulus)
    - [Container Injection](#container-injection)
    - [Request Injection](#request-injection)
    - [Configuration Repository Injection](#configuration-repository-injection)
- [Managing Memory Leaks](#managing-memory-leaks)
- [Concurrent Tasks](#concurrent-tasks)
- [Ticks and Intervals](#ticks-and-intervals)
- [The Stimulus Cache](#the-stimulus-cache)
    - [Cache Intervals](#cache-intervals)
- [Tables](#tables)

<a name="introduction"></a>
## Introduction

[Stimulus](https://github.com/crustum/stimulus) supercharges your CakePHP application's performance by serving your application using high-powered application servers, including [FrankenPHP](https://frankenphp.dev/), [RoadRunner](https://roadrunner.dev), [Swoole](https://github.com/swoole/swoole-src), and [Open Swoole](https://openswoole.com/). Stimulus boots your application once, keeps it in memory, and then feeds it requests at supersonic speeds.

Where PHP-FPM boots the framework for every request, Stimulus boots it once inside a long-lived worker and reuses that instance across requests. That is where the speed comes from — and why request-specific state must be reset between requests instead of assumed fresh.

<a name="quickstart"></a>
## Quickstart

### Installing the Plugin

Install via Composer:

```bash
composer require crustum/stimulus
```

> [!NOTE]
> This plugin should be registered in your `config/plugins.php` file.

```bash
bin/cake plugin load Crustum/Stimulus
```

> [!TIP]
> **After the plugin registers itself**, it's recommended to install the configuration with the manifest system:

```bash
bin/cake manifest install --plugin Crustum/Stimulus
```

The Stimulus plugin will create the `config/stimulus.php` configuration file and append the loading of the `config/stimulus.php` file to the `config/bootstrap.php` file:

```php
if (file_exists(CONFIG . 'stimulus.php')) {
    Configure::load('stimulus', 'default');
}
```

Alternatively, you can load the plugin in your `src/Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/Stimulus');
}
```

This will enable Stimulus by registering its `stimulus install`, `stimulus start`, `stimulus reload`, `stimulus stop`, and `stimulus status` commands and setting up the worker state reset listeners.

All of your application's Stimulus configuration is stored in the `config/stimulus.php` configuration file:

```php
return [
    'Stimulus' => [
        'server' => env('STIMULUS_SERVER', 'roadrunner'),
        'host' => env('STIMULUS_HOST', '127.0.0.1'),
        'port' => env('STIMULUS_PORT', '8000'),
        'appClass' => env('STIMULUS_APP_CLASS', 'App\\Application'),
        'https' => env('STIMULUS_HTTPS', false),
        'max_execution_time' => 30,
        'max_requests' => 500,
        'garbage' => 50,
        'watch' => [
            'src',
            'config/**/*.php',
            'resources/**/*.php',
            'templates/**/*.php',
            'tests/**/*.php',
            'composer.lock',
            '.env',
        ],
    ],
];
```

<a name="quickstart-next-steps"></a>
#### Next Steps

Once the configuration is installed, pick a server below and run the installer. Then you're ready to learn more about [serving your application](#serving-your-application) and building [Stimulus friendly](#dependency-injection-and-stimulus) code.

<a name="installation"></a>
## Installation

Stimulus may be installed via the Composer package manager, as shown above. After installing the plugin and publishing the configuration, execute the `stimulus install` command, which installs the selected server's dependencies, records the server in your `.env` file, and publishes the plugin configuration through the manifest installer:

```shell
bin/cake stimulus install
```

You will be asked which application server you would like to use (`frankenphp`, `roadrunner`, or `swoole`). To skip the interactive choice, pass the server explicitly:

```shell
bin/cake stimulus install --server=roadrunner
bin/cake stimulus install --server=frankenphp
bin/cake stimulus install --server=swoole
```

Use `--force` to overwrite existing configuration files:

```shell
bin/cake stimulus install --server=roadrunner --force
```

When the install succeeds, Stimulus:

1. Installs the server dependencies (RoadRunner / FrankenPHP binary, Swoole extension check).
2. Appends missing entries to your `.gitignore` (`rr`, `.rr.yaml` for RoadRunner; `frankenphp`, `frankenphp-worker.php` for FrankenPHP).
3. Writes `STIMULUS_SERVER=<server>` to your `.env` file (appending it when missing, rewriting it when present).
4. Publishes the plugin configuration via the manifest installer (`manifest install --plugin Crustum/Stimulus --tag config`).

<a name="server-prerequisites"></a>
## Server Prerequisites

<a name="frankenphp"></a>
### FrankenPHP

[FrankenPHP](https://frankenphp.dev) is a PHP application server, written in Go, that supports modern web features like early hints, Brotli, and Zstandard compression. When you install Stimulus and choose FrankenPHP as your server, Stimulus ensures the FrankenPHP binary and the `frankenphp-worker.php` worker script are installed for you.

```shell
bin/cake stimulus install --server=frankenphp
```

#### Custom Caddyfile Configuration

When using FrankenPHP, you may specify a custom Caddyfile using the `--caddyfile` option when starting Stimulus:

```shell
bin/cake stimulus start --server=frankenphp --caddyfile=/path/to/your/Caddyfile
```

This allows you to customize FrankenPHP's configuration beyond the default settings, such as adding custom middleware, configuring advanced routing, or setting up custom directives. You may consult the [official Caddy documentation](https://caddyserver.com/docs/caddyfile) for more information on Caddyfile syntax and configuration options.

<a name="roadrunner"></a>
### RoadRunner

[RoadRunner](https://roadrunner.dev) is powered by the RoadRunner binary, which is built using Go. When you install Stimulus and choose RoadRunner, Stimulus ensures the RoadRunner Composer packages and binary are installed for you.

```shell
bin/cake stimulus install --server=roadrunner
```

The installer appends `rr` and `.rr.yaml` to your `.gitignore` when that file exists.

You may customize the worker binary path and HTTP middleware in `config/stimulus.php`, which is useful for zero-downtime deployments where the application is served from a symlinked `current` path:

```php
'roadrunner' => [
    'command' => env('STIMULUS_ROADRUNNER_COMMAND', 'vendor/bin/roadrunner-worker'),
    'http_middleware' => env('STIMULUS_ROADRUNNER_HTTP_MIDDLEWARE', 'static'),
],
```

To use a custom RoadRunner configuration file when starting the server, pass `--rr-config`:

```shell
bin/cake stimulus start --server=roadrunner --rr-config=/path/to/.rr.yaml
```

<a name="swoole"></a>
### Swoole

If you plan to use the Swoole application server to serve your Stimulus application, you must install the Swoole PHP extension. Typically, this can be done via PECL:

```shell
pecl install swoole
```

```shell
bin/cake stimulus install --server=swoole
```

The installer warns when the Swoole extension is missing. The extension itself is installed outside Composer, so the install still succeeds and the server will refuse to start until the extension is present.

<a name="open-swoole"></a>
#### Open Swoole

If you want to use the Open Swoole application server to serve your Stimulus application, you must install the Open Swoole PHP extension. Typically, this can be done via PECL:

```shell
pecl install openswoole
```

Using Stimulus with Open Swoole grants the same functionality provided by Swoole, such as concurrent tasks, ticks, and intervals.

<a name="swoole-configuration"></a>
#### Swoole Configuration

Swoole supports additional configuration options that you may add to your `stimulus` configuration file if necessary. Because they rarely need to be modified, these options are not included in the default configuration file:

```php
'Stimulus' => [
    // ...
    'swoole' => [
        'command' => env('STIMULUS_SWOLE_COMMAND', ''),
        'php_options' => [],
        'ssl' => env('STIMULUS_SWOLE_SSL', false),
        'options' => [
            'log_file' => TMP . 'logs' . DIRECTORY_SEPARATOR . 'swoole_http.log',
            'package_max_length' => 10 * 1024 * 1024,
        ],
    ],
],
```

The Swoole log file defaults below the application `tmp/logs` directory, and Stimulus creates that directory before the server boots when it does not exist.

<a name="serving-your-application"></a>
## Serving Your Application

The Stimulus server can be started via the `stimulus start` command. By default, this command utilizes the server specified by the `server` configuration option of your application's `stimulus` configuration file (or the `STIMULUS_SERVER` environment variable):

```shell
bin/cake stimulus start
```

You may override the server, host, and port per invocation:

```shell
bin/cake stimulus start --server=roadrunner --host=127.0.0.1 --port=8000
```

By default, Stimulus will start the server on port 8000, so you may access your application in a web browser via `http://localhost:8000`.

> [!WARNING]
> Workers hold the booted application in memory. After changing code, providers, environment variables, or configuration, reload the workers (or restart the server, or run with `--watch` during local development) — otherwise the old code keeps serving requests.

<a name="serving-your-application-via-https"></a>
### Serving Your Application via HTTPS

By default, applications running via Stimulus generate links prefixed with `http://`. The `STIMULUS_HTTPS` environment variable, used within your application's `config/stimulus.php` configuration file, can be set to `true` when serving your application via HTTPS. When this configuration value is set to `true`, Stimulus will instruct CakePHP to generate all absolute links using `https://`:

```php
'https' => env('STIMULUS_HTTPS', false),
```

```shell
bin/cake stimulus start --server=frankenphp --https
```

<a name="serving-your-application-via-nginx"></a>
### Serving Your Application via Nginx

In production environments, you should serve your Stimulus application behind a traditional web server such as Nginx or Apache. Doing so will allow the web server to serve your static assets such as images and stylesheets, as well as manage your SSL certificate termination.

In the Nginx configuration example below, Nginx will serve the site's static assets and proxy requests to the Stimulus server that is running on port 8000:

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    listen [::]:80;
    server_name domain.com;
    server_tokens off;
    root /var/virtual/domain.com/webroot;

    charset utf-8;

    location / {
        try_files $uri @stimulus;
        expires max;
        access_log off;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log off;
    error_log /var/virtual/domain.com/logs/server_error.log error;

    location @stimulus {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;

        proxy_pass http://127.0.0.1:8000;
    }

    location = /index.php {
        deny all;
    }
}
```

When Nginx terminates SSL, set `STIMULUS_HTTPS=true` so Stimulus generates `https://` links, and set your canonical URL:

```php
// In config/app.php
'App' => [
    // ...
    'fullBaseUrl' => 'https://domain.com',
],
```

```ini
# In .env
STIMULUS_HTTPS=true
```

<a name="watching-for-file-changes"></a>
### Watching for File Changes

Since your application is loaded in memory once when the Stimulus server starts, any changes to your application's files will not be reflected when you refresh your browser. For convenience, you may use the `--watch` flag to instruct Stimulus to automatically reload the server on any file changes within your application:

```shell
bin/cake stimulus start --watch
```

Use `--poll` when watching files over a network where native file events are unreliable:

```shell
bin/cake stimulus start --watch --poll
```

You may configure the directories and files that should be watched using the `watch` configuration option within your application's `config/stimulus.php` configuration file.

<a name="specifying-the-worker-count"></a>
### Specifying the Worker Count

By default, Stimulus will start an application request worker for each CPU core provided by your machine. These workers will then be used to serve incoming HTTP requests as they enter your application. You may manually specify how many workers you would like to start using the `--workers` option when invoking the `stimulus start` command:

```shell
bin/cake stimulus start --workers=4
```

If you are using the Swoole application server, you may also specify how many "task workers" you wish to start:

```shell
bin/cake stimulus start --server=swoole --workers=4 --task-workers=6
```

<a name="specifying-the-max-request-count"></a>
### Specifying the Max Request Count

To help prevent stray memory leaks, Stimulus stops a worker once it has handled 500 failing operations, which keeps a repeatedly failing worker from looping forever. To adjust the recycle behavior, you may use the `--max-requests` option:

```shell
bin/cake stimulus start --max-requests=250
```

The same value is available as the `max_requests` key in `config/stimulus.php`. Setting it to `0` disables the limit. Treat this as the last line of defense against leaks, not a substitute for freeing what you allocate.

<a name="specifying-the-max-execution-time"></a>
### Specifying the Max Execution Time

By default, Stimulus sets a maximum execution time of 30 seconds for incoming requests via the `max_execution_time` option in your application's `config/stimulus.php` configuration file:

```php
'max_execution_time' => 30,
```

This setting defines the maximum number of seconds that an incoming request is allowed to execute before being terminated. Setting this value to `0` will disable the execution time limit entirely. This configuration option is particularly useful for applications that handle long-running requests, such as file uploads, data processing, or API calls to external services.

> [!WARNING]
> When you modify the `max_execution_time` configuration, you must restart the Stimulus server for the changes to take effect.

<a name="reloading-the-workers"></a>
### Reloading the Workers

You may gracefully reload the Stimulus server's application workers using the `stimulus reload` command. Typically, this should be done after deployment so that your newly deployed code is loaded into memory and is used to serve subsequent requests:

```shell
bin/cake stimulus reload
bin/cake stimulus reload --server=roadrunner
```

<a name="stopping-the-server"></a>
### Stopping the Server

You may stop the Stimulus server using the `stimulus stop` command:

```shell
bin/cake stimulus stop
bin/cake stimulus stop --server=swoole
```

<a name="checking-the-server-status"></a>
#### Checking the Server Status

You may check the current status of the Stimulus server using the `stimulus status` command:

```shell
bin/cake stimulus status
bin/cake stimulus status --server=frankenphp
```

<a name="dependency-injection-and-stimulus"></a>
## Dependency Injection and Stimulus

Since Stimulus boots your application once and keeps it in memory while serving requests, there are a few caveats you should consider while building your application. For example, long-lived service wiring will only be executed once when the worker initially boots. On subsequent requests, the same application instance is reused, with per-request state reset by the listeners configured under `Stimulus.listeners` in `config/stimulus.php`.

Octane-style "register and boot run once" applies here as well: you should take special care when injecting the application service container or request into any object's constructor. By doing so, that object may hold a stale version of the container or request on subsequent requests.

Stimulus automatically resets first-party framework state between requests via its worker listeners (configuration sandbox, router, cache, session, authentication, locale, translator, query log, and more). However, Stimulus does not always know how to reset the global state created by your application. Therefore, you should be aware of how to build your application in a way that is Stimulus friendly. Below, we discuss the most common situations that may cause problems while using Stimulus.

<a name="container-injection"></a>
### Container Injection

In general, you should avoid injecting the application service container into the constructors of shared (long-lived) objects. If that instance is resolved during the worker boot process, the container snapshot held by the service will be reused on subsequent requests and may miss bindings added later.

As a work-around, you could either scope the service per request (or list it in the `flush` bindings in `config/stimulus.php` so the container resolves it again), or you could inject a container resolver closure into the service that always resolves the current container instance.

<a name="request-injection"></a>
### Request Injection

In general, you should avoid injecting the HTTP request instance into the constructors of shared objects. If the service instance is resolved during the worker boot process, the HTTP request will be injected into the service and that same request will be held by the service instance on subsequent requests. Therefore, all headers, input, and query string data will be incorrect, as well as all other request data.

As a work-around, pass the specific request information your object needs to one of the object's methods at runtime, or read the current request back per request instead of capturing it at construction:

```php
// Prefer this...
$service->handle($request->getData('name'));

// ...over capturing $request in a shared service constructor.
```

> [!WARNING]
> It is acceptable to type-hint the `Cake\Http\ServerRequest` instance on your controller methods and route closures.

Do not read PHP superglobals (`$_GET`, `$_POST`, `$_SERVER`) in worker code. They are not reliably populated under workers — use the request object instead.

<a name="configuration-repository-injection"></a>
### Configuration Repository Injection

In general, you should avoid capturing the configuration repository instance in the constructors of shared objects. If the configuration values change between requests, that service will not see the new values because it depends on the original repository snapshot.

Stimulus mitigates this with the `CreateConfigurationSandbox` listener, which sandboxes `Cake\Core\Configure` per operation. Still, prefer reading configuration at runtime (`Configure::read()`) after the application has booted, and never resolve configuration in low-level worker boot code before the application is fully bootstrapped.

<a name="managing-memory-leaks"></a>
### Managing Memory Leaks

Remember, Stimulus keeps your application in memory between requests; therefore, adding data to a statically maintained array will result in a memory leak. For example, the following controller has a memory leak since each request to the application will continue to add data to the static `$data` array:

```php
class ReportsController extends AppController
{
    protected static array $data = [];

    public function index()
    {
        static::$data[] = bin2hex(random_bytes(5));

        // ...
    }
}
```

While building your application, you should take special care to avoid creating these types of memory leaks. It is recommended that you monitor your application's memory usage during local development to ensure you are not introducing new memory leaks into your application.

Practical defenses:

- Set a worker recycle limit (`max_requests` / `--max-requests`) as the last line of defense.
- Register event listeners in plugin bootstrap, never inside request handlers — the event manager keeps every one.
- Clear static arrays in a lifecycle listener or `flush` the service. Never append to them in request handlers.
- Watch for shared services that capture large objects (entities) in closures, since they are held for the worker's lifetime.

<a name="concurrent-tasks"></a>
## Concurrent Tasks

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may execute operations concurrently via light-weight background task workers. First read `Configure::read('Stimulus.server')` to confirm the `swoole` driver, then resolve tasks through the `StimulusManager`. You may combine this with PHP array destructuring to retrieve the results of each operation:

```php
use Crustum\Stimulus\StimulusManager;

[$users, $orders] = $manager->concurrently([
    fn () => $usersTable->find()->all(),
    fn () => $ordersTable->find()->where(['status' => 'pending'])->all(),
]);
```

The `concurrently()` method accepts an array of closures and an optional wait timeout in milliseconds (default `3000`):

```php
$results = $manager->concurrently($tasks, 3000);
```

Results are keyed by their given keys. Concurrent tasks processed by Stimulus utilize Swoole's task workers, which execute within an entirely different process than the incoming request. The number of workers available to process concurrent tasks is determined by the `--task-workers` directive on the `stimulus start` command:

```shell
bin/cake stimulus start --server=swoole --workers=4 --task-workers=6
```

Rules for task closures:

- Pass scalar IDs and re-fetch entities inside the closure. Do not capture `$this`, entities, PDO connections, or other non-serializable state, because closures are serialized for task workers.
- Catch exceptions inside closures when you need to transform or log the error before the manager rethrows it as a task exception.
- Without Swoole the same calls resolve sequentially in-process via the `SequentialTaskDispatcher`; test correctness, not parallelism.

<a name="ticks-and-intervals"></a>
## Ticks and Intervals

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may register "tick" operations that will be executed on worker ticks. You may register tick callbacks via the manager's `tick` method. The first argument is a string key identifying the ticker, the second is the callable, followed by the interval in seconds and whether the callback also runs on the first tick:

```php
$manager->tick('simple-ticker', function (): void {
    // ...
}, seconds: 10, immediate: true);
```

On Swoole / Open Swoole hosts the registration returns a due-checked `InvokeTickCallable` listener with interval bookkeeping; without the extension the callback runs on every received tick instead. Guard tick registration behind the driver check (`Configure::read('Stimulus.server') === 'swoole'`) and test correctness rather than timing when no real server is running.

<a name="the-stimulus-cache"></a>
## The Stimulus Cache

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may leverage the Stimulus cache store, which is powered by a Swoole table shared across all workers on the server. All data stored in the cache is available to all workers on the server. However, the cached data will be flushed when the server is restarted — treat it as ephemeral, high-frequency storage, not durable caching.

You may set the maximum number of rows as well as the number of bytes per row using the `cache` configuration options in `config/stimulus.php`:

```php
'cache' => [
    'rows' => 1000,
    'bytes' => 10000,
],
```

> [!NOTE]
> The maximum number of entries allowed in the Stimulus cache may be defined in your application's `stimulus` configuration file.

<a name="cache-intervals"></a>
### Cache Intervals

In addition to the typical get / put operations, the Stimulus cache store (`Crustum\Stimulus\Cache\StimulusStore`) features interval based caches. These caches are automatically refreshed at the specified interval. For example, the following cache will be refreshed every five seconds:

```php
$store->interval('random', function (): string {
    return bin2hex(random_bytes(5));
}, 5);
```

Interval resolvers are serializable closures persisted alongside their refresh metadata; reads resolve pending values through `get()`, and flushes preserve the `interval-*` resolver rows so registered intervals survive flushes.

<a name="tables"></a>
## Tables

> [!WARNING]
> This feature requires [Swoole](#swoole).

When using Swoole, you may define and interact with your own arbitrary [Swoole tables](https://www.swoole.co.uk/docs/modules/swoole-table). Swoole tables provide extreme performance throughput and the data in these tables can be accessed by all workers on the server. However, the data within them will be lost when the server is restarted.

Tables should be defined within the `tables` configuration array of your application's `stimulus` configuration file. An example table that allows a maximum of 1000 rows is already configured for you. The maximum size of string columns may be configured by specifying the column size after the column type as seen below:

```php
'tables' => [
    'example:1000' => [
        'name' => 'string:1000',
        'votes' => 'int',
    ],
],
```

To access a table, you may use the manager's `table` method. Table access without the Swoole server raises, and unconfigured table names raise with their name:

```php
$table = $manager->table('example');

$table->set('uuid', [
    'name' => 'Nuno Maduro',
    'votes' => 1000,
]);

return $table->get('uuid');
```

Rules for tables:

- Pre-size tables at boot (`'name:maxRows'`). You cannot resize them, and exceeding the maximum may fail silently.
- Use `incr()` / `decr()` for atomic counters — there are no transactions.
- Treat tables as volatile: data is lost on worker restart, so never use them as a database replacement.

> [!WARNING]
> The column types supported by Swoole tables are: `string`, `int`, and `float`.
