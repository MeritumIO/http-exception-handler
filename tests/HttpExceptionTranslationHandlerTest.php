<?php

namespace Meritum\HttpExceptionHandler\Test;

use Meritum\Http\Exception\HttpExceptionInterface;
use Meritum\HttpExceptionHandler\HttpDomainException;
use Meritum\HttpExceptionHandler\HttpExceptionTranslationHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

final class HttpExceptionTranslationHandlerTest extends TestCase
{
    private HttpExceptionTranslationHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new HttpExceptionTranslationHandler();
    }

    private function makeHttpException(): HttpExceptionInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/');
        $uri->method('getQuery')->willReturn('');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        return new class($request) extends \RuntimeException implements HttpExceptionInterface {
            public function __construct(private readonly ServerRequestInterface $request)
            {
                parent::__construct('Not found');
            }

            public function getStatusCode(): int { return 404; }
            public function getTitle(): string { return 'Not Found'; }
            public function getRequest(): ServerRequestInterface { return $this->request; }
        };
    }

    #[Test]
    public function test_matches_http_exception_interface(): void
    {
        $this->assertTrue($this->handler->matches($this->makeHttpException()));
    }

    #[Test]
    public function test_does_not_match_non_http_exception(): void
    {
        $this->assertFalse($this->handler->matches(new RuntimeException()));
    }

    #[Test]
    public function test_handle_returns_http_domain_exception(): void
    {
        $result = $this->handler->handle($this->makeHttpException());

        $this->assertInstanceOf(HttpDomainException::class, $result);
    }

    #[Test]
    public function test_priority_is_zero(): void
    {
        $this->assertSame(0, $this->handler->priority());
    }
}
