# inertia-psr15 Mezzio flash bridge

[![Latest Stable Version](http://poser.pugx.org/sirix/inertia-psr15-mezzio-flash/v)](https://packagist.org/packages/sirix/inertia-psr15-mezzio-flash) [![Total Downloads](http://poser.pugx.org/sirix/inertia-psr15-mezzio-flash/downloads)](https://packagist.org/packages/sirix/inertia-psr15-mezzio-flash) [![Latest Unstable Version](http://poser.pugx.org/sirix/inertia-psr15-mezzio-flash/v/unstable)](https://packagist.org/packages/sirix/inertia-psr15-mezzio-flash) [![License](http://poser.pugx.org/sirix/inertia-psr15-mezzio-flash/license)](https://packagist.org/packages/sirix/inertia-psr15-mezzio-flash) [![PHP Version Require](http://poser.pugx.org/sirix/inertia-psr15-mezzio-flash/require/php)](https://packagist.org/packages/sirix/inertia-psr15-mezzio-flash)

Storage-neutral Mezzio flash-message integration for [`sirix/inertia-psr15`](https://packagist.org/packages/sirix/inertia-psr15) 3.x. It adapts `Mezzio\Flash\FlashMessagesInterface` to the core public flash-provider contract. It does not install middleware, select a session backend, transform payloads, or write logs.

## Installation

```shell
composer require sirix/inertia-psr15-mezzio-flash
```

The package is auto-discovered by laminas-component-installer. Ensure the session and flash middleware run before Inertia for every applicable path:

```php
$app->pipe(\Mezzio\Session\SessionMiddleware::class);
$app->pipe(\Mezzio\Flash\FlashMessageMiddleware::class);
$app->pipe(\Sirix\InertiaPsr15\Middleware\InertiaMiddleware::class);
```

The default request attribute is `FlashMessageMiddleware::FLASH_ATTRIBUTE` (`flash`). Missing or incorrectly typed attributes fail fast with a typed bridge exception, which usually means the pipeline order or route coverage is incorrect.

## Usage

For a response rendered in the same request, use the core API:

```php
$inertia->flash('message', 'Profile saved');

return $inertia->render('Profile/Edit', ['profile' => $profile]);
```

For post-redirect-get, queue the flash value before returning a redirect:

```php
$inertia->flash(['message' => 'User created', 'userId' => $user->id]);

return new \Laminas\Diactoros\Response\RedirectResponse('/users/' . $user->id);
```

Incoming Mezzio messages and direct values are exposed in Inertia v3's top-level `page.flash`; they are not copied to `props.flash`. On control responses (`409` with `X-Inertia-Location` or `X-Inertia-Redirect`), the core persists pending direct data before this bridge prolongs incoming messages.

## Configuration

The configuration provider registers only `InertiaFlashProviderInterface` and defaults to the `flash` request attribute. To use a different attribute name:

```php
return [
    'inertia_psr15_mezzio_flash' => [
        'request_attribute' => 'application_flash',
    ],
];
```

The value must be a non-empty, control-character-free string. The adapter returns the native Mezzio payload unchanged; core validates safe keys and serializability.

## Contributing

Run all checks with:

```shell
composer check
```

See [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), and [CHANGELOG.md](CHANGELOG.md).
