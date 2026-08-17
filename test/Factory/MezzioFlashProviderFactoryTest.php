<?php

declare(strict_types=1);

namespace InertiaPsr15MezzioFlashTest\Factory;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Flash\FlashMessagesInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashConfigurationException;
use Sirix\InertiaPsr15MezzioFlash\Factory\MezzioFlashProviderFactory;
use Sirix\InertiaPsr15MezzioFlash\MezzioFlashProvider;

/** @internal */
#[CoversNothing]
final class MezzioFlashProviderFactoryTest extends TestCase
{
    public function testCreatesProviderWhenConfigServiceIsAbsent(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with('config')->willReturn(false);

        self::assertInstanceOf(MezzioFlashProvider::class, (new MezzioFlashProviderFactory())($container));
    }

    public function testCreatesProviderWithAValidCustomAttribute(): void
    {
        $container = $this->containerWithConfig([
            'inertia_psr15_mezzio_flash' => [
                'request_attribute' => 'custom_flash',
            ],
        ]);
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->method('getFlashes')->willReturn([
            'notice' => 'Saved',
        ]);

        $provider = (new MezzioFlashProviderFactory())($container);

        self::assertSame([
            'notice' => 'Saved',
        ], $provider->pull((new ServerRequest())->withAttribute('custom_flash', $messages)));
    }

    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfiguration(mixed $config): void
    {
        $container = $this->containerWithConfig($config);

        $this->expectException(MezzioFlashConfigurationException::class);
        $this->expectExceptionMessage('Configuration');
        (new MezzioFlashProviderFactory())($container);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidConfigs(): iterable
    {
        yield 'non-array config service' => ['invalid'];

        yield 'non-array bridge config' => [[
            'inertia_psr15_mezzio_flash' => 'invalid',
        ]];

        yield 'empty request attribute' => [[
            'inertia_psr15_mezzio_flash' => [
                'request_attribute' => '',
            ],
        ]];

        yield 'non-string request attribute' => [[
            'inertia_psr15_mezzio_flash' => [
                'request_attribute' => 1,
            ],
        ]];

        yield 'control character in request attribute' => [[
            'inertia_psr15_mezzio_flash' => [
                'request_attribute' => "flash\nname",
            ],
        ]];
    }

    public function testWrapsTheConfigContainerExceptionWithoutLeakingIt(): void
    {
        $failure   = new class('sensitive-value') extends RuntimeException implements ContainerExceptionInterface {};
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('config')->willReturn(true);
        $container->method('get')->with('config')->willThrowException($failure);

        try {
            (new MezzioFlashProviderFactory())($container);
            self::fail('Expected config lookup failure.');
        } catch (MezzioFlashConfigurationException $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringNotContainsString('sensitive-value', $exception->getMessage());
        }
    }

    private function containerWithConfig(mixed $config): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('config')->willReturn(true);
        $container->method('get')->with('config')->willReturn($config);

        return $container;
    }
}
