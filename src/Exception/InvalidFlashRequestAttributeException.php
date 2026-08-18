<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash\Exception;

use Mezzio\Flash\FlashMessagesInterface;

use function sprintf;

final class InvalidFlashRequestAttributeException extends AbstractMezzioFlashException
{
    public function __construct(string $requestAttribute)
    {
        parent::__construct(sprintf(
            'The request flash attribute "%s" must implement %s.',
            $requestAttribute,
            FlashMessagesInterface::class,
        ));
    }
}
