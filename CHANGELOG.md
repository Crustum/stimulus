# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0]

- Fix redaction of the authorization query parameter in FrankenPHP access logs.
- List the call-site memoization flusher among the default operation listeners.
- Fall back to the configured server when stopping before command arguments are bound.
- Support Mercure 1.0 hubs: exclude the hub from compression, require FrankenPHP 1.13.0, and derive the hub configuration from the default broadcasting connection unless overridden.
- Leave FrankenPHP binaries outside the project root untouched, noting the manual update, and require FrankenPHP 1.13.2.
- Accept RoadRunner 3.x binaries in the version check.
- Stream generator-produced responses on FrankenPHP, Swoole, and RoadRunner, stopping quietly on client disconnect.

## [1.0.0]

Initial release of `crustum/stimulus` (`Crustum\Stimulus`).
