<?php

namespace Meritum\HttpExceptionHandler\Test;

use Meritum\Http\Exception\ExceptionHandlerInterface;
use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\HttpExceptionHandler\ExceptionHandler;
use Meritum\StructuredLogging\Exception\DomainException;
use Meritum\StructuredLogging\ExceptionReporter;
use Meritum\StructuredLogging\Severity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ExceptionHandlerTest extends TestCase
{
    private function makeDomainException(
        string $errorCode = 'UNKNOWN_0000',
        string $message = 'Something went wrong',
        ?\Throwable $previous = null,
    ): DomainException {
        return new class($errorCode, $message, $previous) extends DomainException {
            public function __construct(
                private readonly string $errorCode,
                string $message,
                ?\Throwable $previous,
            ) {
                parent::__construct($message, Severity::Error, [], false, 0, $previous);
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
                parent::__construct('Resource not found');
            }

            public function getStatusCode(): int { return $this->status; }
            public function getTitle(): string { return $this->title; }
            public function getRequest(): ServerRequestInterface { return $this->request; }
        };
    }

    private function makeReporter(DomainException $returns): ExceptionReporter
    {
        $reporter = $this->createMock(ExceptionReporter::class);
        $reporter->method('report')->willReturn($returns);

        return $reporter;
    }

    #[Test]
    public function test_implements_exception_handler_interface(): void
    {
        $handler = new ExceptionHandler($this->makeReporter($this->makeDomainException()));

        $this->assertInstanceOf(ExceptionHandlerInterface::class, $handler);
    }

    #[Test]
    public function test_returns_response_interface(): void
    {
        $handler = new ExceptionHandler($this->makeReporter($this->makeDomainException()));

        $response = $handler->handle(new RuntimeException(), $this->createStub(ServerRequestInterface::class));

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function test_response_status_code_from_http_exception(): void
    {
        $domain = $this->makeDomainException(previous: $this->makeHttpException(status: 422));
        $handler = new ExceptionHandler($this->makeReporter($domain));

        $response = $handler->handle(new RuntimeException(), $this->createStub(ServerRequestInterface::class));

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function test_response_status_code_is_500_for_unknown_exception(): void
    {
        $domain = $this->makeDomainException(previous: new RuntimeException());
        $handler = new ExceptionHandler($this->makeReporter($domain));

        $response = $handler->handle(new RuntimeException(), $this->createStub(ServerRequestInterface::class));

        $this->assertSame(500, $response->getStatusCode());
    }

    #[Test]
    public function test_response_body_is_json(): void
    {
        $domain = $this->makeDomainException(previous: $this->makeHttpException(status: 404, title: 'Not Found'));
        $handler = new ExceptionHandler($this->makeReporter($domain));

        $response = $handler->handle(new RuntimeException(), $this->createStub(ServerRequestInterface::class));
        $body = json_decode((string) $response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('code', $body);
        $this->assertArrayHasKey('status', $body);
        $this->assertArrayHasKey('title', $body);
        $this->assertArrayHasKey('detail', $body);
    }

    #[Test]
    public function test_response_body_contains_envelope_data(): void
    {
        $httpException = $this->makeHttpException(status: 404, title: 'Not Found');
        $domain = $this->makeDomainException(errorCode: 'HTTP_404', message: 'Page not found', previous: $httpException);
        $handler = new ExceptionHandler($this->makeReporter($domain));

        $response = $handler->handle(new RuntimeException(), $this->createStub(ServerRequestInterface::class));
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame('HTTP_404', $body['code']);
        $this->assertSame(404, $body['status']);
        $this->assertSame('Not Found', $body['title']);
        $this->assertSame('Page not found', $body['detail']);
    }

    #[Test]
    public function test_reporter_is_called_with_original_exception(): void
    {
        $original = new RuntimeException('original');
        $reporter = $this->createMock(ExceptionReporter::class);
        $reporter->expects($this->once())
            ->method('report')
            ->with($original)
            ->willReturn($this->makeDomainException());

        $handler = new ExceptionHandler($reporter);
        $handler->handle($original, $this->createStub(ServerRequestInterface::class));
    }
}
