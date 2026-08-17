<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15MezzioFlash\Exception;

use Mezzio\Flash\FlashMessagesInterface;

use function sprintf;

final class MissingFlashRequestAttributeException extends MezzioFlashConfigurationException
{
    public function __construct(string $requestAttribute)
    {
        parent::__construct(sprintf(
            'The request is missing flash attribute "%s", which must implement %s.',
            $requestAttribute,
            FlashMessagesInterface::class,
        ));
    }
}
