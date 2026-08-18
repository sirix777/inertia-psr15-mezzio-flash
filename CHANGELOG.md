# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-18

### Added

- Mezzio `FlashMessagesInterface` adapter for the public Inertia PSR-15 flash provider contract.
- Laminas configuration provider with an opt-in flash provider service mapping.
- Typed bridge exceptions for invalid request attributes, configuration, and Mezzio operations.
- Flash lifecycle, core integration, configuration, and exception-chain test coverage.
