# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- PHP 8.6 support.

### Changed

- Minimum supported PHP version is now 8.3 (dropped 8.2); supported versions are ~8.3 to ~8.6.
- Raised the `sirix/inertia-psr15` requirement to `^3.1`, the first release supporting PHP 8.6.
- Tooling: Rector and PHP-CS-Fixer now target PHP 8.3; typed class constants and `readonly` anonymous classes adopted.
- Tests: PHPUnit raised to `^12.5`; mocks without expectations replaced with stubs.

## [1.0.0] - 2026-08-18

### Added

- Mezzio `FlashMessagesInterface` adapter for the public Inertia PSR-15 flash provider contract.
- Laminas configuration provider with an opt-in flash provider service mapping.
- Typed bridge exceptions for invalid request attributes, configuration, and Mezzio operations.
- Flash lifecycle, core integration, configuration, and exception-chain test coverage.
