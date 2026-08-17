<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash\Exception;

use RuntimeException;
use Throwable;

class MezzioFlashConfigurationException extends RuntimeException implements MezzioFlashExceptionInterface
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
