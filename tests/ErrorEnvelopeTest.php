<?php

namespace Meritum\HttpExceptionHandler\Test;

use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\HttpExceptionHandler\ErrorEnvelope;
use Meritum\StructuredLogging\Exception\DomainException;
use Meritum\StructuredLogging\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ErrorEnvelopeTest extends TestCase
{
    private function makeDomainException(
        string $errorCode = 'HTTP_404',
        string $message = 'Resource not found',
        ?\Throwable $previous = null,
    ): DomainException {
        return new class($errorCode, $message, $previous) extends DomainException {
            public function __construct(
                private readonly string $errorCode,
                string $message,
                ?\Throwable $previous,
            ) {
                parent::__construct($message, Severity::Debug, [], false, 0, $previous);
            }

            public function getErrorCode(): string { return $this->errorCode; }
        };
    }

    private function makeHttpException(int $status = 404, string $title = 'Not Found'): HttpExceptionInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);

        return new class($status, $title, $request) extends RuntimeException implements HttpExceptionInterface {
            public function __construct(
                private readonly int $status,
                private readonly string $title,
                private readonly ServerRequestInterface $request,
            ) {
                parent::__construct('Not found');
            }

            public function getStatusCode(): int { return $this->status; }
            public function getTitle(): string { return $this->title; }
            public function getRequest(): ServerRequestInterface { return $this->request; }
        };
    }

    #[Test]
    public function test_constructor_sets_properties(): void
    {
        $envelope = new ErrorEnvelope('HTTP_404', 404, 'Not Found', 'Resource not found');

        $this->assertSame('HTTP_404', $envelope->code);
        $this->assertSame(404, $envelope->status);
        $this->assertSame('Not Found', $envelope->title);
        $this->assertSame('Resource not found', $envelope->detail);
    }

    #[Test]
    public function test_detail_defaults_to_null(): void
    {
        $envelope = new ErrorEnvelope('HTTP_404', 404, 'Not Found');

        $this->assertNull($envelope->detail);
    }

    #[Test]
    public function test_json_serialize_shape(): void
    {
        $envelope = new ErrorEnvelope('HTTP_422', 422, 'Unprocessable Entity', 'Validation failed');

        $this->assertSame([
            'code'   => 'HTTP_422',
            'status' => 422,
            'title'  => 'Unprocessable Entity',
            'detail' => 'Validation failed',
        ], $envelope->jsonSerialize());
    }

    #[Test]
    public function test_json_serialize_includes_null_detail(): void
    {
        $envelope = new ErrorEnvelope('HTTP_404', 404, 'Not Found');

        $this->assertArrayHasKey('detail', $envelope->jsonSerialize());
        $this->assertNull($envelope->jsonSerialize()['detail']);
    }

    #[Test]
    public function test_from_domain_exception_with_http_previous(): void
    {
        $httpException = $this->makeHttpException(status: 422, title: 'Unprocessable Entity');
        $domain = $this->makeDomainException(
            errorCode: 'HTTP_422',
            message: 'Validation failed',
            previous: $httpException,
        );

        $envelope = ErrorEnvelope::fromDomainException($domain);

        $this->assertSame('HTTP_422', $envelope->code);
        $this->assertSame(422, $envelope->status);
        $this->assertSame('Unprocessable Entity', $envelope->title);
        $this->assertSame('Validation failed', $envelope->detail);
    }

    #[Test]
    public function test_from_domain_exception_without_http_previous_uses_500(): void
    {
        $domain = $this->makeDomainException(previous: new RuntimeException('Something broke'));

        $envelope = ErrorEnvelope::fromDomainException($domain);

        $this->assertSame(500, $envelope->status);
    }

    #[Test]
    public function test_from_domain_exception_without_http_previous_uses_unexpected_error_title(): void
    {
        $domain = $this->makeDomainException(previous: new RuntimeException());

        $envelope = ErrorEnvelope::fromDomainException($domain);

        $this->assertSame('Unexpected Error', $envelope->title);
    }

    #[Test]
    public function test_from_domain_exception_without_previous_uses_500(): void
    {
        $domain = $this->makeDomainException();

        $envelope = ErrorEnvelope::fromDomainException($domain);

        $this->assertSame(500, $envelope->status);
        $this->assertSame('Unexpected Error', $envelope->title);
    }

    #[Test]
    public function test_from_domain_exception_uses_error_code_from_domain(): void
    {
        $domain = $this->makeDomainException(errorCode: 'HTTP_503', previous: $this->makeHttpException(status: 503, title: 'Service Unavailable'));

        $envelope = ErrorEnvelope::fromDomainException($domain);

        $this->assertSame('HTTP_503', $envelope->code);
    }

    #[Test]
    public function test_from_domain_exception_uses_message_as_detail(): void
    {
        $domain = $this->makeDomainException(message: 'The requested resource was not found');

        $envelope = ErrorEnvelope::fromDomainException($domain);

        $this->assertSame('The requested resource was not found', $envelope->detail);
    }
}
