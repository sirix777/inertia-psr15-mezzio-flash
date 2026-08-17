<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class MezzioFlashOperationException extends RuntimeException implements MezzioFlashExceptionInterface
{
    public function __construct(private readonly string $operation, Throwable $previous)
    {
        parent::__construct(sprintf('The Mezzio flash provider failed during %s.', $operation), previous: $previous);
    }

    public function operation(): string
    {
        return $this->operation;
    }
}
