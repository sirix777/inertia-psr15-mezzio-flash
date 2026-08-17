<?php

declare(strict_types=1);

namespace InertiaPsr15MezzioFlashTest\Exception;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\InvalidFlashRequestAttributeException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashConfigurationException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashExceptionInterface;
use Sirix\InertiaPsr15MezzioFlash\Exception\MezzioFlashOperationException;
use Sirix\InertiaPsr15MezzioFlash\Exception\MissingFlashRequestAttributeException;

/** @internal */
#[CoversNothing]
final class MezzioFlashExceptionHierarchyTest extends TestCase
{
    public function testAllPublicBridgeExceptionsImplementTheMarkerInterface(): void
    {
        foreach ([
            new MezzioFlashConfigurationException('invalid configuration'),
            new MissingFlashRequestAttributeException('flash'),
            new InvalidFlashRequestAttributeException('flash'),
            new MezzioFlashOperationException('pull', new RuntimeException('sensitive-value')),
        ] as $exception) {
            self::assertInstanceOf(MezzioFlashExceptionInterface::class, $exception);
        }
    }

    public function testOperationExceptionKeepsTheCauseWithoutExposingItsMessage(): void
    {
        $cause     = new RuntimeException('sensitive-value');
        $exception = new MezzioFlashOperationException('persist', $cause);

        self::assertSame('persist', $exception->operation());
        self::assertSame($cause, $exception->getPrevious());
        self::assertStringNotContainsString('sensitive-value', $exception->getMessage());
    }
}
