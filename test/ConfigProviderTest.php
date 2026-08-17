<?php

declare(strict_types=1);

namespace InertiaPsr15MezzioFlashTest;

use Mezzio\Flash\FlashMessageMiddleware;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Sirix\InertiaPsr15\Service\InertiaFlashProviderInterface;
use Sirix\InertiaPsr15MezzioFlash\ConfigProvider;
use Sirix\InertiaPsr15MezzioFlash\Factory\MezzioFlashProviderFactory;

/** @internal */
#[CoversNothing]
final class ConfigProviderTest extends TestCase
{
    public function testProvidesOnlyTheFlashProviderFactoryAndDefaultRequestAttribute(): void
    {
        $config = (new ConfigProvider())();

        self::assertSame([
            InertiaFlashProviderInterface::class => MezzioFlashProviderFactory::class,
        ], $config['dependencies']['factories']);
        self::assertSame(FlashMessageMiddleware::FLASH_ATTRIBUTE, $config['inertia_psr15_mezzio_flash']['request_attribute']);
    }
}
