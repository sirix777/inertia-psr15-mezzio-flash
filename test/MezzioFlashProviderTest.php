<?php

declare(strict_types=1);

namespace InertiaPsr15MezzioFlashTest;

use Laminas\Diactoros\ServerRequest;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\InvalidFlashRequestAttributeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashOperationException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MissingFlashRequestAttributeException;
use Sirix\InertiaPsr15MezzioFlash\MezzioFlashProvider;
use stdClass;

/** @internal */
#[CoversNothing]
final class MezzioFlashProviderTest extends TestCase
{
    public function testPullReadsFlashesFromTheDefaultRequestAttributeWithoutTransformingThem(): void
    {
        $flash = [
            'notice' => [
                'text' => 'Saved',
            ],
        ];
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('getFlashes')->willReturn($flash);

        $result = (new MezzioFlashProvider())->pull(
            (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages),
        );

        self::assertSame($flash, $result);
    }

    public function testUsesTheConfiguredRequestAttribute(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('getFlashes')->willReturn([
            'message' => 'Saved',
        ]);

        self::assertSame([
            'message' => 'Saved',
        ], (new MezzioFlashProvider('custom_flash'))->pull(
            (new ServerRequest())->withAttribute('custom_flash', $messages),
        ));
    }

    public function testMissingRequestAttributeFailsWithoutLeakingPayload(): void
    {
        try {
            (new MezzioFlashProvider('custom_flash'))->pull(new ServerRequest());
        } catch (MissingFlashRequestAttributeException $exception) {
            self::assertStringContainsString('custom_flash', $exception->getMessage());
            self::assertStringNotContainsString('sensitive-value', $exception->getMessage());

            return;
        }

        self::fail('The missing request attribute must fail fast.');
    }

    public function testWrongRequestAttributeTypeFailsFast(): void
    {
        $request = (new ServerRequest())->withAttribute('custom_flash', new stdClass());

        $this->expectException(InvalidFlashRequestAttributeException::class);
        $this->expectExceptionMessage('custom_flash');
        $this->expectExceptionMessage(FlashMessagesInterface::class);

        (new MezzioFlashProvider('custom_flash'))->pull($request);
    }

    public function testEmptyPersistDoesNotResolveTheRequestAttribute(): void
    {
        (new MezzioFlashProvider())->persist(new ServerRequest(), []);

        self::addToAssertionCount(1);
    }

    public function testPersistFlashesEveryItemWithTheDefaultHopCount(): void
    {
        $calls    = [];
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::exactly(2))->method('flash')->willReturnCallback(
            static function(string $key, mixed $value) use (&$calls): void {
                $calls[] = [$key, $value];
            },
        );

        (new MezzioFlashProvider())->persist(
            (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages),
            [
                'notice' => 'Saved',
                'count'  => 2,
            ],
        );

        self::assertSame([['notice', 'Saved'], ['count', 2]], $calls);
    }

    public function testPreserveProlongsIncomingFlashExactlyOnce(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('prolongFlash');

        (new MezzioFlashProvider())->preserve(
            (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages),
        );
    }

    public function testOperationFailuresAreWrappedOnceWithoutLeakingTheOriginalMessage(): void
    {
        foreach ([
            'pull'     => 'getFlashes',
            'persist'  => 'flash',
            'preserve' => 'prolongFlash',
        ] as $operation => $method) {
            $failure  = new RuntimeException('sensitive-value');
            $messages = $this->createMock(FlashMessagesInterface::class);
            $messages->method($method)->willThrowException($failure);
            $request = new ServerRequest();

            try {
                match ($operation) {
                    'pull'     => (new MezzioFlashProvider())->pull($request->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages)),
                    'persist'  => (new MezzioFlashProvider())->persist($request->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages), [
                        'notice' => 'Saved',
                    ]),
                    'preserve' => (new MezzioFlashProvider())->preserve($request->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages)),
                };
                self::fail('The Mezzio operation failure was not wrapped.');
            } catch (MezzioFlashOperationException $exception) {
                self::assertSame($operation, $exception->operation());
                self::assertSame($failure, $exception->getPrevious());
                self::assertStringNotContainsString('sensitive-value', $exception->getMessage());
            }
        }
    }
}
