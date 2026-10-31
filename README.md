# CakePHP Stimulus Plugin

The **Stimulus** plugin supercharges your CakePHP application's performance by serving it with high-powered application servers, including FrankenPHP, RoadRunner, Swoole, and Open Swoole.

Stimulus boots your application once, keeps it in memory, and feeds it requests at supersonic speeds instead of rebooting the framework for every request like PHP-FPM.

The plugin resets framework state between requests through worker listeners, and provides concurrent task dispatching, tick handlers, a shared Swoole-table cache, and Swoole tables for data shared across workers.

The plugin integrates seamlessly with CakePHP's application lifecycle and provides command line tools for installing, starting, reloading, stopping, and checking the status of the server.

## Requirements

* PHP 8.5+

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org):

```
composer require crustum/stimulus
```

Load the plugin:

```
bin/cake plugin load Crustum/Stimulus
```

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
