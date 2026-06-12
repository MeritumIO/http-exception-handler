<?php

namespace Meritum\HttpExceptionHandler;

use Meritum\StructuredLogging\Severity;
use Psr\Http\Message\ServerRequestInterface;
use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\StructuredLogging\Exception\DomainException;

final class HttpDomainException extends DomainException
{
    public function __construct(HttpExceptionInterface $exception)
    {
        $status = $exception->getStatusCode();

        $context = self::getRequestContext($exception->getRequest());

        parent::__construct(
            $exception->getMessage(),
            self::determineSeverity($status),
            $context,
            false,
            $status,
            $exception
        );
    }

    public function getErrorCode(): string
    {
        return 'HTTP_' . $this->getCode();
    }

    private static function determineSeverity(int $statusCode): Severity
    {
        return match (true) {
            $statusCode === 503 => Severity::Debug,
            $statusCode >= 500  => Severity::Error,
            default             => Severity::Debug
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function getRequestContext(ServerRequestInterface $request): array
    {
        $uri = $request->getUri();

        return [
            'request_method' => $request->getMethod(),
            'request_path'   => $uri->getPath(),
            'request_query'  => $uri->getQuery(),
        ];
    }
}
