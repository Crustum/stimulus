---
name: stimulus-development
description: "Use this skill when working with Crustum Stimulus, a long-running CakePHP worker server (Swoole, FrankenPHP, RoadRunner) where the application boots once and serves many requests instead of rebooting for each request like PHP-FPM. Trigger when installing Stimulus or starting its server; configuring or detecting the active driver for driver-specific code; using StimulusManager::concurrently(), table(), the stimulus cache, or shared in-memory state across workers; controlling worker memory growth; or testing Stimulus behavior. Skip plain PHP-FPM applications with no persistent worker."
license: MIT
metadata:
  author: crustum
---

# Crustum Stimulus

Stimulus boots the CakePHP application once and reuses it across requests in a long-running worker. Follow these rules to avoid worker-specific bugs caused by PHP-FPM assumptions.

## Detect the Driver First

Read `Configure::read('Stimulus.server')` to determine the driver: `swoole`, `roadrunner`, or `frankenphp`. Guard `StimulusManager::concurrently()`, `table()`, the `stimulus` cache store, and ticks or intervals behind this check, because they run on Swoole only.

## State Isolation (all drivers)

Avoid storing request-specific state in shared services or static properties. State set during one request can survive into the next, which is the most common source of worker bugs.

- Scope, lazily resolve, or add to the `flush` list (`config/stimulus.php`) any service that holds data derived from the request, the identity, or another per-request value.
- Inject the request through method parameters. Do not capture it at construction, where it goes stale — read it back from `CurrentApplication` instead.
- Reset any static state you must keep in a `Stimulus.requestTerminated` listener, or list the service in `flush`. Stimulus already flushes core state such as authentication, sessions, and cookies. Your application code is your responsibility.

## Memory (all drivers)

Free what you allocate. Workers are long-lived, so anything left referenced can accumulate until the worker crashes.

- Set a max-requests recycle limit (`stimulus.stop` / `--max-requests`) as the last line of defense against leaks.
- Register event listeners in plugin bootstrap, never inside request handlers, since the event manager keeps every one.
- Clear static arrays in a lifecycle listener or `flush` the service. Never append to them in request handlers.
- Watch for shared services that capture large objects (entities) in closures, since they are held forever.

## Concurrency (Swoole only)

Guard the driver, then run closures in parallel:

```php
[$users, $orders] = $manager->concurrently([
    fn () => $usersTable->find()->all(),
    fn () => $ordersTable->find()->where(['status' => 'pending'])->all(),
]);
```

- Pass scalar IDs and re-fetch entities inside the closure. Do not capture `$this` or non-serializable state, because closures are serialized for task workers.
- Expect results in input order. Catch exceptions inside closures when you need to transform or log the error before the manager rethrows it.
- Without Swoole the same calls resolve sequentially in-process; test correctness, not parallelism.

## Swoole Tables and `stimulus` Cache (Swoole only)

- Pre-size tables at boot (`'name:maxRows'`). You cannot resize them, and exceeding the maximum may fail silently. Treat them as volatile because data is lost on worker restart; do not use them as a database replacement.
- Use `incr()` and `decr()` for atomic counters, since there are no transactions.
- Use the `stimulus` cache store only for ephemeral, high-frequency data. It uses the same shared memory and is not durable caching.

## Testing (all drivers)

- Test correctness rather than parallelism when exercising `concurrently()`, tables, and ticks without a real server, since closures may run sequentially in tests.
- Boot a fresh sandbox (`createStimulusContext()`) between assertions to simulate the per-request flush and prove state does not leak.
- Guard Swoole-specific tests with `extension_loaded('swoole') || extension_loaded('openswoole')` and skip them when neither extension is available.

## Common Pitfalls

These issues recur when developing applications on Stimulus. Guard against each one as you write code.

- Avoid resolving `Configure::read()` or other container services in low-level worker boot code before the application is fully bootstrapped. Resolve configuration after the application is booted.
- Never capture the identity in a shared service or static property. It can leave one user logged in for later requests on the same worker, even after their session is cleared. Resolve authentication state per request and add stateful services to the `flush` list. See [State Isolation](#state-isolation-all-drivers).
- Close per-request database and HTTP client connections you open outside the managed pools. The worker does not close them, so they can accumulate until the worker recycles. Reset them in a `Stimulus.requestTerminated` listener.
- Do not read PHP superglobals. `$_GET`, `$_POST`, and `$_SERVER` are not reliably populated under workers. Use the request object instead.
- Do not capture non-serializable values in a `concurrently()` closure. A `PDO` connection, entity, or `$this` can trigger a serialization error. Pass scalar IDs and re-fetch entities inside the closure.
- Fix PHP warnings emitted during request startup. On FrankenPHP, they can shut down the worker. Resolve the underlying warning rather than suppressing it.
- Reload workers after changing code, providers, environment variables, or configuration. Workers hold the booted application in memory and pick up changes only after `stimulus.reload`, a restart, or running with `--watch` in local development.

## References

Verify driver-specific details against the official docs, because versions and configuration keys change:

- Crustum Stimulus: `docs/` in this plugin (`docs/plan-p8.md`, `config/stimulus.php`)
- FrankenPHP: https://frankenphp.dev/docs/ (worker mode: `/docs/worker/`, config/Caddyfile: `/docs/config/`, known issues: `/docs/known-issues/`)
- OpenSwoole: https://openswoole.com/docs
- RoadRunner: https://docs.roadrunner.dev/docs (PHP workers: `/docs/php-worker/worker.md`, intro: `/docs/general/about.md`)

## Verification

1. Confirm `Configure::read('Stimulus.server')` matches the driver assumed by any Swoole-only code.
2. Confirm no request-derived state lives in a shared service or static without `flush` or a reset.
3. Confirm a worker recycle limit (`max-requests`) is configured.
