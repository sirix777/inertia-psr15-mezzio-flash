# Contributing

Issues and pull requests are welcome. Please keep changes focused, include tests for behavior changes, and run the project checks before opening a pull request:

```shell
composer check
```

The package deliberately adapts only the Mezzio flash contract to the public `inertia-psr15` flash-provider interface. Changes that add storage backends, pipeline registration, payload transformation, or logging are out of scope unless proposed and agreed separately.

Use PHP 8.2-compatible syntax and preserve typed, payload-safe exception diagnostics.
