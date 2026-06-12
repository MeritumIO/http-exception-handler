<?php

namespace Meritum\HttpExceptionHandler;

use Throwable;
use Meritum\StructuredLogging\TranslationHandler;
use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\StructuredLogging\Exception\DomainException;

final class HttpExceptionTranslationHandler implements TranslationHandler
{
    public function matches(Throwable $exception): bool
    {
        return $exception instanceof HttpExceptionInterface;
    }

    public function handle(Throwable $exception): DomainException
    {
        assert($exception instanceof HttpExceptionInterface);

        return new HttpDomainException($exception);
    }

    public function priority(): int
    {
        return 0;
    }
}
