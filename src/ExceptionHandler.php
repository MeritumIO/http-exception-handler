<?php

namespace Meritum\HttpExceptionHandler;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Meritum\StructuredLogging\ExceptionReporter;
use Meritum\Http\Contract\ExceptionHandlerInterface;

final class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(private readonly ExceptionReporter $reporter) {}

    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $domain = $this->reporter->report($e);

        $envelope = ErrorEnvelope::fromDomainException($domain);

        return new JsonResponse($envelope, $envelope->status);
    }
}
