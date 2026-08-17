<?php

declare(strict_types=1);

namespace InertiaPsr15MezzioFlashTest\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use JsonSerializable;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Sirix\InertiaPsr15\Exception\InertiaFlashException;
use Sirix\InertiaPsr15\Exception\InertiaSerializationException;
use Sirix\InertiaPsr15\Middleware\InertiaMiddleware;
use Sirix\InertiaPsr15\Model\Page;
use Sirix\InertiaPsr15\Service\InertiaFactory;
use Sirix\InertiaPsr15\Service\InertiaInterface;
use Sirix\InertiaPsr15\Service\InertiaVersionProviderInterface;
use Sirix\InertiaPsr15\View\RootViewProviderInterface;
use Sirix\InertiaPsr15MezzioFlash\Exception\InvalidFlashRequestAttributeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashOperationException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MissingFlashRequestAttributeException;
use Sirix\InertiaPsr15MezzioFlash\MezzioFlashProvider;
use stdClass;

use function json_decode;

/** @internal */
#[CoversNothing]
final class ConsumerFlashLifecycleTest extends TestCase
{
    public function testRenderedPageUsesTheRealBridgeAndDirectFlashWinsOnKeyCollision(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('getFlashes')->willReturn([
            'incoming' => 'From previous request',
            'message'  => 'Old message',
        ]);
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_GET, 'php://memory', [
            'X-Inertia' => 'true',
        ]))
            ->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages)
        ;
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                $inertia->flash('message', 'Direct message');

                return $inertia->render('Projects');
            }
        };

        $response = $this->middleware()->process($request, $handler);
        $page     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([
            'incoming' => 'From previous request',
            'message'  => 'Direct message',
        ], $page['flash']);
        self::assertArrayNotHasKey('flash', $page['props']);
    }

    public function testEmptyIncomingFlashDoesNotCreateThePageFlashField(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('getFlashes')->willReturn([]);
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_GET, 'php://memory', [
            'X-Inertia' => 'true',
        ]))->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

                return $inertia->render('Projects');
            }
        };

        $response = $this->middleware()->process($request, $handler);
        $page     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('flash', $page);
    }

    public function testPrgPersistsThroughTheRealBridgeForTheNextHop(): void
    {
        $calls    = [];
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('flash')->willReturnCallback(
            static function(string $key, mixed $value) use (&$calls): void { $calls[] = [$key, $value]; },
        );
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_GET, 'php://memory', [
            'X-Inertia' => 'true',
        ]))->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                $inertia->flash('message', 'Saved');

                return (new Response())->withStatus(StatusCodeInterface::STATUS_FOUND)->withHeader('Location', '/projects');
            }
        };

        $this->middleware()->process($request, $handler);

        self::assertSame([['message', 'Saved']], $calls);
    }

    public function testEarlyVersionMismatchDoesNotPullOrHandleButPreservesOnce(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::never())->method('getFlashes');
        $messages->expects(self::once())->method('prolongFlash');
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_GET, 'php://memory', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => 'stale',
        ]))->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
        $version = $this->createMock(InertiaVersionProviderInterface::class);
        $version->method('currentVersion')->willReturn('current');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->middleware($version)->process($request, $handler);

        self::assertSame(StatusCodeInterface::STATUS_CONFLICT, $response->getStatusCode());
    }

    public function testExternalLocationPersistsBeforePreservingIncomingFlash(): void
    {
        $calls    = [];
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->method('flash')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'persist'; });
        $messages->method('prolongFlash')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'preserve'; });
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_POST, 'php://memory', [
            'X-Inertia' => 'true',
        ]))
            ->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages)
        ;
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                $inertia->flash('message', 'Saved');

                return $inertia->location('https://example.test/projects');
            }
        };

        $this->middleware()->process($request, $handler);

        self::assertSame(['persist', 'preserve'], $calls);
    }

    public function testFragmentRedirectPersistsBeforePreservingIncomingFlash(): void
    {
        $calls    = [];
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->method('flash')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'persist'; });
        $messages->method('prolongFlash')->willReturnCallback(static function() use (&$calls): void { $calls[] = 'preserve'; });
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_POST, 'php://memory', [
            'X-Inertia' => 'true',
        ]))
            ->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages)
        ;
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                $inertia->flash('message', 'Saved');

                return (new Response())->withStatus(StatusCodeInterface::STATUS_FOUND)->withHeader('Location', '/projects#details');
            }
        };

        $response = $this->middleware()->process($request, $handler);

        self::assertSame('/projects#details', $response->getHeaderLine('X-Inertia-Redirect'));
        self::assertSame(['persist', 'preserve'], $calls);
    }

    public function testMissingBridgeAttributeIsNestedInTheCoreFlashExceptionWithoutPayloadLeakage(): void
    {
        $request = new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_GET, 'php://memory', [
            'X-Inertia' => 'true',
        ]);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

                return $inertia->render('Projects');
            }
        };

        try {
            $this->middleware()->process($request, $handler);
            self::fail('Expected missing flash attribute failure.');
        } catch (InertiaFlashException $exception) {
            self::assertInstanceOf(MissingFlashRequestAttributeException::class, $exception->getPrevious());
            self::assertStringNotContainsString('sensitive-value', $exception->getMessage());
        }
    }

    public function testInvalidBridgeAttributeIsNestedInTheCoreFlashException(): void
    {
        $request = (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, new stdClass());
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

                return $inertia->render('Projects');
            }
        };

        try {
            $this->middleware()->process($request, $handler);
            self::fail('Expected invalid flash attribute failure.');
        } catch (InertiaFlashException $exception) {
            self::assertInstanceOf(InvalidFlashRequestAttributeException::class, $exception->getPrevious());
        }
    }

    public function testFlashOperationFailureKeepsTheFullCoreAndBridgeExceptionChain(): void
    {
        $failure  = new RuntimeException('sensitive-value');
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('getFlashes')->willThrowException($failure);
        $request = (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);

                return $inertia->render('Projects');
            }
        };

        try {
            $this->middleware()->process($request, $handler);
            self::fail('Expected flash provider failure.');
        } catch (InertiaFlashException $exception) {
            $bridgeException = $exception->getPrevious();
            self::assertInstanceOf(MezzioFlashOperationException::class, $bridgeException);
            self::assertSame($failure, $bridgeException->getPrevious());
            self::assertStringNotContainsString('sensitive-value', $exception->getMessage());
        }
    }

    public function testInvalidUtf8FlashIsRenderedUsingTheCoreSerializerSemantics(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::once())->method('getFlashes')->willReturn([]);
        $request = (new ServerRequest([], [], '/projects', RequestMethodInterface::METHOD_GET, 'php://memory', [
            'X-Inertia' => 'true',
        ]))->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                $inertia->flash('message', "\xB1");

                return $inertia->render('Projects');
            }
        };

        $response = $this->middleware()->process($request, $handler);
        $page     = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame("\u{FFFD}", $page['flash']['message']);
    }

    public function testUnserializableFlashIsRejectedByCoreBeforeTheBridgePersistsIt(): void
    {
        $messages = $this->createMock(FlashMessagesInterface::class);
        $messages->expects(self::never())->method('flash');
        $failure        = new RuntimeException('sensitive-value');
        $unserializable = new class($failure) implements JsonSerializable {
            public function __construct(private readonly RuntimeException $failure) {}

            public function jsonSerialize(): mixed
            {
                throw $this->failure;
            }
        };
        $request = (new ServerRequest())->withAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE, $messages);
        $handler = new class($unserializable) implements RequestHandlerInterface {
            public function __construct(private readonly mixed $value) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var InertiaInterface $inertia */
                $inertia = $request->getAttribute(InertiaMiddleware::INERTIA_ATTRIBUTE);
                $inertia->flash('private', $this->value);

                return (new Response())->withStatus(StatusCodeInterface::STATUS_FOUND)->withHeader('Location', '/projects');
            }
        };

        $this->expectException(InertiaSerializationException::class);
        $this->middleware()->process($request, $handler);
    }

    private function middleware(?InertiaVersionProviderInterface $version = null): InertiaMiddleware
    {
        $root = new class implements RootViewProviderInterface {
            public function __invoke(Page $page): string
            {
                return '<html></html>';
            }
        };

        return new InertiaMiddleware(
            new InertiaFactory(new ResponseFactory(), new StreamFactory(), $root),
            versionProvider: $version,
            flashProvider: new MezzioFlashProvider(),
        );
    }
}
