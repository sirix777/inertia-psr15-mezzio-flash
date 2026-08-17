<?php

declare(strict_types=1);

namespace InertiaPsr15MezzioFlashTest\Integration;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessages;
use Mezzio\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15MezzioFlash\MezzioFlashProvider;

/** @internal */
#[CoversNothing]
final class FlashLifecycleTest extends TestCase
{
    public function testRealMezzioFlashMessagesAdvanceAcrossExactlyOnePreservedHop(): void
    {
        $session  = new ArraySession();
        $provider = new MezzioFlashProvider();

        $first = FlashMessages::createFromSession($session);
        $provider->persist($this->requestWith($first), [
            'message' => 'Saved',
        ]);
        self::assertSame([], $first->getFlashes());

        $second = FlashMessages::createFromSession($session);
        self::assertSame([
            'message' => 'Saved',
        ], $provider->pull($this->requestWith($second)));
        $provider->preserve($this->requestWith($second));

        $third = FlashMessages::createFromSession($session);
        self::assertSame([
            'message' => 'Saved',
        ], $provider->pull($this->requestWith($third)));

        $fourth = FlashMessages::createFromSession($session);
        self::assertSame([], $provider->pull($this->requestWith($fourth)));
    }

    private function requestWith(object $messages): ServerRequest
    {
        return (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
    }
}

final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private bool $changed = false;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $name, $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function set(string $name, $value): void
    {
        $this->data[$name] = $value;
        $this->changed     = true;
    }

    public function unset(string $name): void
    {
        unset($this->data[$name]);
        $this->changed = true;
    }

    public function clear(): void
    {
        $this->data    = [];
        $this->changed = true;
    }

    public function hasChanged(): bool
    {
        return $this->changed;
    }

    public function regenerate(): SessionInterface
    {
        return $this;
    }

    public function isRegenerated(): bool
    {
        return false;
    }
}
