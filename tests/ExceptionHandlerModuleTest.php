<?php

namespace Meritum\HttpExceptionHandler\Test;

use Georgeff\Kernel\DI\TagRegistryInterface;
use Georgeff\Kernel\Environment;
use Georgeff\Kernel\Kernel;
use Meritum\Http\Exception\ExceptionHandlerInterface;
use Meritum\HttpExceptionHandler\ExceptionHandlerModule;
use Meritum\HttpExceptionHandler\HttpExceptionTranslationHandler;
use Meritum\StructuredLogging\StructuredLoggingModule;
use Meritum\StructuredLogging\TranslationHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ExceptionHandlerModuleTest extends TestCase
{
    private function makeKernel(): Kernel
    {
        $kernel = new Kernel(Environment::Testing);
        $kernel->define(LoggerInterface::class, fn() => new NullLogger());
        $kernel->addModule(new StructuredLoggingModule());
        $kernel->addModule(new ExceptionHandlerModule());
        $kernel->boot();

        return $kernel;
    }

    #[Test]
    public function test_exception_handler_resolves(): void
    {
        $container = $this->makeKernel()->getContainer();

        $this->assertInstanceOf(ExceptionHandlerInterface::class, $container->get(ExceptionHandlerInterface::class));
    }

    #[Test]
    public function test_translation_handler_resolves(): void
    {
        $container = $this->makeKernel()->getContainer();

        $this->assertInstanceOf(HttpExceptionTranslationHandler::class, $container->get(HttpExceptionTranslationHandler::class));
    }

    #[Test]
    public function test_translation_handler_is_tagged(): void
    {
        $container = $this->makeKernel()->getContainer();
        $tags = $container->get(TagRegistryInterface::class);

        $handlers = $tags->getTagged('exception.translator.handlers');

        $this->assertNotEmpty($handlers);
        $this->assertContainsOnlyInstancesOf(TranslationHandler::class, $handlers);
    }

    #[Test]
    public function test_http_translation_handler_is_in_tagged_handlers(): void
    {
        $container = $this->makeKernel()->getContainer();
        $tags = $container->get(TagRegistryInterface::class);

        $handlers = $tags->getTagged('exception.translator.handlers');
        $types = array_map(fn($h) => $h::class, $handlers);

        $this->assertContains(HttpExceptionTranslationHandler::class, $types);
    }
}
