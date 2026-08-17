<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash;

use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sirix\InertiaPsr15\Service\InertiaFlashProviderInterface;
use Sirix\InertiaPsr15MezzioFlash\Exception\InvalidFlashRequestAttributeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashExceptionInterface;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashOperationException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MissingFlashRequestAttributeException;
use Throwable;

final readonly class MezzioFlashProvider implements InertiaFlashProviderInterface
{
    public function __construct(private string $requestAttribute = FlashMessageMiddleware::FLASH_ATTRIBUTE) {}

    public function pull(ServerRequestInterface $request): array
    {
        $messages = $this->messagesFrom($request);

        try {
            return $messages->getFlashes();
        } catch (Throwable $exception) {
            throw $this->operationException('pull', $exception);
        }
    }

    public function persist(ServerRequestInterface $request, array $flash): void
    {
        if ([] === $flash) {
            return;
        }

        $messages = $this->messagesFrom($request);

        try {
            foreach ($flash as $key => $value) {
                $messages->flash($key, $value);
            }
        } catch (Throwable $exception) {
            throw $this->operationException('persist', $exception);
        }
    }

    public function preserve(ServerRequestInterface $request): void
    {
        $messages = $this->messagesFrom($request);

        try {
            $messages->prolongFlash();
        } catch (Throwable $exception) {
            throw $this->operationException('preserve', $exception);
        }
    }

    private function messagesFrom(ServerRequestInterface $request): FlashMessagesInterface
    {
        $messages = $request->getAttribute($this->requestAttribute);

        if (null === $messages) {
            throw new MissingFlashRequestAttributeException($this->requestAttribute);
        }

        if (! $messages instanceof FlashMessagesInterface) {
            throw new InvalidFlashRequestAttributeException($this->requestAttribute);
        }

        return $messages;
    }

    private function operationException(string $operation, Throwable $exception): Throwable
    {
        if ($exception instanceof MezzioFlashExceptionInterface) {
            return $exception;
        }

        return new MezzioFlashOperationException($operation, $exception);
    }
}
