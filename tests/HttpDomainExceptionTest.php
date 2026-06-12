<?php

namespace Meritum\HttpExceptionHandler\Test;

use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\HttpExceptionHandler\HttpDomainException;
use Meritum\StructuredLogging\Exception\DomainException;
use Meritum\StructuredLogging\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

final class HttpDomainExceptionTest extends TestCase
{
    private function makeRequest(
        string $method = 'GET',
        string $path = '/test',
        string $query = '',
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn($query);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function makeHttpException(
        int $status = 404,
        string $title = 'Not Found',
        string $message = 'Resource not found',
        ?ServerRequestInterface $request = null,
    ): HttpExceptionInterface {
        $resolvedRequest = $request ?? $this->makeRequest();

        return new class($status, $title, $message, $resolvedRequest) extends \RuntimeException implements HttpExceptionInterface {
            public function __construct(
                private readonly int $status,
                private readonly string $title,
                string $message,
                private readonly ServerRequestInterface $request,
            ) {
                parent::__construct($message);
            }

            public function getStatusCode(): int { return $this->status; }
            public function getTitle(): string { return $this->title; }
            public function getRequest(): ServerRequestInterface { return $this->request; }
        };
    }

    #[Test]
    public function test_extends_domain_exception(): void
    {
        $exception = new HttpDomainException($this->makeHttpException());

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    #[Test]
    public function test_message_comes_from_http_exception(): void
    {
        $exception = new HttpDomainException($this->makeHttpException(message: 'Page not found'));

        $this->assertSame('Page not found', $exception->getMessage());
    }

    #[Test]
    public function test_code_is_http_status(): void
    {
        $exception = new HttpDomainException($this->makeHttpException(status: 422));

        $this->assertSame(422, $exception->getCode());
    }

    #[Test]
    public function test_previous_is_original_http_exception(): void
    {
        $httpException = $this->makeHttpException();
        $exception = new HttpDomainException($httpException);

        $this->assertSame($httpException, $exception->getPrevious());
    }

    #[Test]
    public function test_not_retryable(): void
    {
        $exception = new HttpDomainException($this->makeHttpException());

        $this->assertFalse($exception->retryable);
    }

    #[Test]
    public function test_error_code_format(): void
    {
        $exception = new HttpDomainException($this->makeHttpException(status: 404));

        $this->assertSame('HTTP_404', $exception->getErrorCode());
    }

    #[Test]
    public function test_error_code_uses_status_code(): void
    {
        $exception = new HttpDomainException($this->makeHttpException(status: 503));

        $this->assertSame('HTTP_503', $exception->getErrorCode());
    }

    #[Test]
    public function test_severity_is_debug_for_4xx(): void
    {
        foreach ([400, 401, 403, 404, 422, 429] as $status) {
            $exception = new HttpDomainException($this->makeHttpException(status: $status));

            $this->assertSame(Severity::Debug, $exception->severity, "Expected Debug for status {$status}");
        }
    }

    #[Test]
    public function test_severity_is_error_for_5xx(): void
    {
        foreach ([500, 502, 504] as $status) {
            $exception = new HttpDomainException($this->makeHttpException(status: $status));

            $this->assertSame(Severity::Error, $exception->severity, "Expected Error for status {$status}");
        }
    }

    #[Test]
    public function test_severity_is_debug_for_503(): void
    {
        $exception = new HttpDomainException($this->makeHttpException(status: 503));

        $this->assertSame(Severity::Debug, $exception->severity);
    }

    #[Test]
    public function test_context_contains_request_method(): void
    {
        $exception = new HttpDomainException(
            $this->makeHttpException(request: $this->makeRequest(method: 'POST'))
        );

        $this->assertSame('POST', $exception->context['request_method']);
    }

    #[Test]
    public function test_context_contains_request_path(): void
    {
        $exception = new HttpDomainException(
            $this->makeHttpException(request: $this->makeRequest(path: '/users/42'))
        );

        $this->assertSame('/users/42', $exception->context['request_path']);
    }

    #[Test]
    public function test_context_contains_request_query(): void
    {
        $exception = new HttpDomainException(
            $this->makeHttpException(request: $this->makeRequest(query: 'page=2&per_page=50'))
        );

        $this->assertSame('page=2&per_page=50', $exception->context['request_query']);
    }

    #[Test]
    public function test_context_query_is_empty_string_when_no_query_params(): void
    {
        $exception = new HttpDomainException(
            $this->makeHttpException(request: $this->makeRequest(query: ''))
        );

        $this->assertSame('', $exception->context['request_query']);
    }

    #[Test]
    public function test_context_has_exactly_three_keys(): void
    {
        $exception = new HttpDomainException($this->makeHttpException());

        $this->assertSame(['request_method', 'request_path', 'request_query'], array_keys($exception->context));
    }
}
