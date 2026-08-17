<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash;

use Mezzio\Flash\FlashMessageMiddleware;
use Sirix\InertiaPsr15\Service\InertiaFlashProviderInterface;
use Sirix\InertiaPsr15MezzioFlash\Factory\MezzioFlashProviderFactory;

final class ConfigProvider
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'dependencies'                => $this->getDependencies(),
            'inertia_psr15_mezzio_flash'  => [
                'request_attribute' => FlashMessageMiddleware::FLASH_ATTRIBUTE,
            ],
        ];
    }

    /** @return array{factories: array<class-string, class-string>} */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                InertiaFlashProviderInterface::class => MezzioFlashProviderFactory::class,
            ],
        ];
    }
}
