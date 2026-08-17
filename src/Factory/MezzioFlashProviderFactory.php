<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash\Factory;

use Mezzio\Flash\FlashMessageMiddleware;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashConfigurationException;
use Sirix\InertiaPsr15MezzioFlash\MezzioFlashProvider;

use function array_key_exists;
use function is_array;
use function is_string;
use function preg_match;

final class MezzioFlashProviderFactory
{
    private const CONFIG_KEY = 'inertia_psr15_mezzio_flash';

    private const REQUEST_ATTRIBUTE_KEY = 'request_attribute';

    public function __invoke(ContainerInterface $container): MezzioFlashProvider
    {
        return new MezzioFlashProvider($this->requestAttribute($this->config($container)));
    }

    /** @return array<string, mixed> */
    private function config(ContainerInterface $container): array
    {
        if (! $container->has('config')) {
            return [];
        }

        try {
            $config = $container->get('config');
        } catch (ContainerExceptionInterface $exception) {
            throw new MezzioFlashConfigurationException(
                'Unable to resolve configuration for the Mezzio flash provider.',
                $exception,
            );
        }

        if (! is_array($config)) {
            throw new MezzioFlashConfigurationException('Configuration service "config" must return an array.');
        }

        return $config;
    }

    /** @param array<string, mixed> $config */
    private function requestAttribute(array $config): string
    {
        if (! array_key_exists(self::CONFIG_KEY, $config)) {
            return FlashMessageMiddleware::FLASH_ATTRIBUTE;
        }

        $bridgeConfig = $config[self::CONFIG_KEY];

        if (! is_array($bridgeConfig)) {
            throw new MezzioFlashConfigurationException(
                'Configuration key "inertia_psr15_mezzio_flash" must be an array.',
            );
        }

        if (! array_key_exists(self::REQUEST_ATTRIBUTE_KEY, $bridgeConfig)) {
            return FlashMessageMiddleware::FLASH_ATTRIBUTE;
        }

        $requestAttribute = $bridgeConfig[self::REQUEST_ATTRIBUTE_KEY];

        if (! is_string($requestAttribute) || '' === $requestAttribute || 1 === preg_match('/[[:cntrl:]]/', $requestAttribute)) {
            throw new MezzioFlashConfigurationException(
                'Configuration key "inertia_psr15_mezzio_flash.request_attribute" must be a non-empty string without control characters.',
            );
        }

        return $requestAttribute;
    }
}
